<?php
/*
 * Form handler for the static demo "clinic-2" (replaces Contact Form 7 + Flamingo from the WordPress version).
 * Validates with the same rules and messages Contact Form 7 used, never sends email (the WordPress forms had
 * skip_mail on), and stores each submission as one JSON line in ../../form-submissions/clinic-2.jsonl, which sits
 * outside public_html. A request with the header "X-FZ-Test: 1" is validated but not stored.
 */
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$FORMS = json_decode('{"13": {"key": "booking", "title": "Visit request", "fields": [["text*", "full-name"], ["tel*", "phone"], ["text*", "patient-type"], ["text*", "service"], ["date*", "preferred-date"], ["textarea", "notes"]]}}', true);

function fz_reply($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    fz_reply(array('status' => 'error', 'message' => 'Method not allowed.'), 405);
}
$id = isset($_GET['form']) ? (string) $_GET['form'] : '';
if (!isset($FORMS[$id])) {
    fz_reply(array('status' => 'error', 'message' => 'The requested contact form was not found.'), 404);
}

$form = $FORMS[$id];
$values = array();
$invalid = array();
foreach ($form['fields'] as $f) {
    $type = rtrim($f[0], '*');
    $required = substr($f[0], -1) === '*';
    $name = $f[1];
    $v = isset($_POST[$name]) ? $_POST[$name] : '';
    if (is_array($v)) {
        $v = implode(', ', $v);
    }
    $v = trim(str_replace("\0", '', (string) $v));
    $values[$name] = $v;
    $max = $type === 'textarea' ? 2000 : 400;
    $msg = null;
    if ($v === '') {
        if ($required) {
            $msg = 'Please fill out this field.';
        }
    } elseif (mb_strlen($v, 'UTF-8') > $max) {
        $msg = 'This field has a too long input.';
    } elseif ($type === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Please enter an email address.';
    } elseif ($type === 'tel' && !preg_match('%^[+]?(?:\([0-9]+\)|[0-9]+)(?:[/ -]*(?:\([0-9]+\)|[0-9]+))*$%', $v)) {
        $msg = 'Please enter a telephone number.';
    } elseif ($type === 'date' && !(preg_match('/^([0-9]{4,})-([0-9]{2})-([0-9]{2})$/', $v, $d) && checkdate((int) $d[2], (int) $d[3], (int) $d[1]))) {
        $msg = 'Please enter a date in YYYY-MM-DD format.';
    }
    if ($msg !== null) {
        $invalid[] = array('field' => $name, 'message' => $msg, 'idref' => null, 'error_id' => 'clinic-2-' . $id . '-' . $name . '-ve');
    }
}
if ($invalid) {
    fz_reply(array(
        'contact_form_id' => (int) $id,
        'status' => 'validation_failed',
        'message' => 'One or more fields have an error. Please check and try again.',
        'invalid_fields' => $invalid,
    ));
}

if (empty($_SERVER['HTTP_X_FZ_TEST'])) {
    $dir = dirname(__DIR__, 2) . '/form-submissions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $file = $dir . '/clinic-2.jsonl';
    if (is_dir($dir) && (!file_exists($file) || filesize($file) < 5000000)) {
        $row = array(
            'time' => gmdate('c'),
            'form' => $form['title'],
            'page' => isset($_SERVER['HTTP_REFERER']) ? substr((string) $_SERVER['HTTP_REFERER'], 0, 300) : '',
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 300) : '',
            'fields' => $values,
        );
        @file_put_contents($file, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    }
}

fz_reply(array(
    'contact_form_id' => (int) $id,
    'status' => 'mail_sent',
    'message' => 'Thank you for your message. It has been sent.',
    'invalid_fields' => array(),
));
