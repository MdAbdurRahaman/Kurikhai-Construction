<?php
// Real CLI/database verification against a disposable MariaDB; provider is controlled.
// This test never connects to SMTP or sends a real message.
namespace PHPMailer\PHPMailer {
    #[\AllowDynamicProperties]
    class PHPMailer {
        public const ENCRYPTION_SMTPS = 'ssl';
        public const ENCRYPTION_STARTTLS = 'tls';
        public static int $calls = 0;
        public static string $recipient = '';
        public function __construct(bool $exceptions = true) {}
        public function isSMTP(): void {}
        public function getSMTPInstance(): object { return (object)['Timeout'=>0,'Timelimit'=>0]; }
        public function setFrom(string $email, string $name): void {}
        public function addAddress(string $email, string $name): void { self::$recipient = $email; }
        public function isHTML(bool $html): void {}
        public function send(): bool { self::$calls++; return true; }
    }
}
namespace {
    if (PHP_SAPI !== 'cli' || getenv('SMTP_VERIFICATION_TEST_DISPOSABLE') !== '1' ||
        !str_starts_with((string)getenv('DB_NAME'), 'tabeeb_test_') ||
        !in_array(getenv('DB_HOST'), ['localhost','127.0.0.1'], true) || getenv('APP_ENV') !== 'production') exit(2);
    require_once __DIR__.'/../includes/db.php';
    $pdo = DB::getConnection();
    if (!$pdo) throw new \RuntimeException('Disposable database unavailable.');
    $role = DB::query("SELECT id FROM roles WHERE slug='super_admin'")->fetchColumn();
    db_exec('INSERT INTO users(name,username,email,password_hash,role_id) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE email=VALUES(email)',
        ['Disposable SMTP fixture','smtp_fixture','smtp_fixture@example.invalid',password_hash('fixture-only', PASSWORD_DEFAULT),$role]);
    db_exec('INSERT INTO settings(setting_group,setting_key,setting_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
        ['fixture','smtp_fixture_unrelated','retain this value']);
    // Include the actual command: its getopt sees the --to argument supplied in CI.
    require __DIR__.'/../scripts/verify_smtp.php';
    $passed = 0;
    $check = function(bool $value) use (&$passed): void {
        if (!$value) throw new \RuntimeException('SMTP verification persistence regression failed.');
        $passed++;
    };
    $check(\PHPMailer\PHPMailer\PHPMailer::$calls === 1);
    $check(\PHPMailer\PHPMailer\PHPMailer::$recipient === 'smtp_fixture@example.invalid');
    $rows = DB::query("SELECT setting_key,setting_value,setting_group,is_public FROM settings WHERE setting_key IN ('smtp_verified_fingerprint','smtp_verified_at')")->fetchAll();
    $check(count($rows) === 2);
    foreach ($rows as $row) {
        $check($row['setting_group'] === 'smtp');
        $check((int)$row['is_public'] === 0);
        $check($row['setting_key'] === 'smtp_verified_fingerprint'
            ? hash_equals(smtp_configuration_fingerprint(), $row['setting_value'])
            : strtotime($row['setting_value']) !== false);
    }
    $check(DB::query("SELECT setting_value FROM settings WHERE setting_key='smtp_fixture_unrelated'")->fetchColumn() === 'retain this value');
    echo json_encode(['passed'=>$passed,'suite'=>'actual SMTP verification CLI, native MariaDB, controlled provider, no real sends'])."\n";
}
