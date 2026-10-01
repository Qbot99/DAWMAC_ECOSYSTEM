<?php
/**
 * Nadaje rolę pracownika istniejącemu kontu giełdy.
 *
 *   php tools/nadaj_role.php jan@dawmac.pl moderator
 *   php tools/nadaj_role.php jan@dawmac.pl admin
 *   php tools/nadaj_role.php jan@dawmac.pl user      (odebranie roli)
 *
 * Konto musi być najpierw założone zwykłą rejestracją. Kolejnych pracowników
 * administrator może dodawać już z panelu (zakładka Użytkownicy).
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Tylko z konsoli.\n");
}

require __DIR__ . '/../api/lib/bootstrap.php';

[$_, $email, $role] = $argv + [null, null, null];
if (!$email || !in_array($role, ['user', 'moderator', 'admin'], true)) {
    exit("Użycie: php tools/nadaj_role.php EMAIL user|moderator|admin\n");
}

$stmt = db()->prepare("UPDATE g_users SET role = ?, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE email = ? AND status <> 'deleted'");
$stmt->execute([$role, mb_strtolower($email)]);

if ($stmt->rowCount() === 0) {
    $exists = db()->prepare('SELECT role FROM g_users WHERE email = ?');
    $exists->execute([mb_strtolower($email)]);
    $current = $exists->fetchColumn();
    exit($current === $role ? "Konto $email już ma rolę $role.\n" : "Nie ma konta $email. Najpierw zarejestruj je na giełdzie.\n");
}
echo "OK: $email ma teraz rolę $role.\n";
