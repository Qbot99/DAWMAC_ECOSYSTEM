<?php
/**
 * Pomocnicze sprawdzanie pól formularzy. Błędy zbieramy per pole,
 * żeby formularz mógł pokazać je przy właściwych polach naraz.
 */

declare(strict_types=1);

final class Fields
{
    public array $errors = [];

    public function __construct(private array $in)
    {
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->in);
    }

    public function str(string $key, int $max, bool $required = false, int $min = 0, string $label = 'Pole'): ?string
    {
        $v = $this->in[$key] ?? null;
        $v = is_scalar($v) ? trim((string) $v) : '';
        // Znaki sterujące poza nową linią i tabulatorem nie mają czego szukać w ogłoszeniu.
        $v = preg_replace('/[^\P{C}\n\t]/u', '', $v) ?? '';
        if ($v === '') {
            if ($required) {
                $this->errors[$key] = "$label jest wymagane.";
            }
            return null;
        }
        $len = mb_strlen($v);
        if ($len > $max) {
            $this->errors[$key] = "$label może mieć najwyżej $max znaków.";
        } elseif ($len < $min) {
            $this->errors[$key] = "$label musi mieć co najmniej $min znaków.";
        }
        return $v;
    }

    public function int(string $key, int $min, int $max, bool $required = false, string $label = 'Pole'): ?int
    {
        $v = $this->in[$key] ?? null;
        if ($v === null || $v === '') {
            if ($required) {
                $this->errors[$key] = "$label jest wymagane.";
            }
            return null;
        }
        $v = filter_var(trim((string) $v), FILTER_VALIDATE_INT);
        if ($v === false) {
            $this->errors[$key] = "$label musi być liczbą całkowitą.";
            return null;
        }
        if ($v < $min || $v > $max) {
            $this->errors[$key] = "$label musi być między $min a $max.";
        }
        return $v;
    }

    public function dec(string $key, float $min, float $max, string $label = 'Pole'): ?float
    {
        $v = $this->in[$key] ?? null;
        if ($v === null || $v === '') {
            return null;
        }
        $v = str_replace(',', '.', trim((string) $v));
        if (!is_numeric($v)) {
            $this->errors[$key] = "$label musi być liczbą.";
            return null;
        }
        $v = round((float) $v, 1);
        if ($v < $min || $v > $max) {
            $this->errors[$key] = "$label musi być między $min a $max.";
        }
        return $v;
    }

    public function bool(string $key): bool
    {
        $v = $this->in[$key] ?? false;
        return $v === true || $v === 1 || in_array((string) $v, ['1', 'true', 'on', 'yes'], true);
    }

    public function enum(string $key, array $allowed, bool $required = false, string $label = 'Pole'): ?string
    {
        $v = $this->in[$key] ?? null;
        if ($v === null || $v === '') {
            if ($required) {
                $this->errors[$key] = "$label jest wymagane.";
            }
            return null;
        }
        if (!in_array($v, $allowed, true)) {
            $this->errors[$key] = "$label ma niedozwoloną wartość.";
            return null;
        }
        return $v;
    }

    public function email(string $key, bool $required = true): ?string
    {
        $v = $this->str($key, 190, $required, 0, 'E-mail');
        if ($v !== null && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$key] = 'Niepoprawny adres e-mail.';
        }
        return $v === null ? null : mb_strtolower($v);
    }

    public function error(string $key, string $message): void
    {
        $this->errors[$key] = $message;
    }

    public function check(): void
    {
        if ($this->errors) {
            fail(422, 'Popraw zaznaczone pola.', $this->errors);
        }
    }
}

/** Rozstaw śrub: 5x112, 5x120.65, 4x100 itd. Zwraca postać kanoniczną. */
function normalize_pcd(?string $pcd): ?string
{
    if ($pcd === null || trim($pcd) === '') {
        return null;
    }
    $pcd = strtolower(str_replace([' ', '×', '*', ','], ['', 'x', 'x', '.'], $pcd));
    if (!preg_match('/^([3-8])x(\d{2,3}(?:\.\d{1,2})?)$/', $pcd, $m)) {
        return '';
    }
    $spacing = str_contains($m[2], '.') ? rtrim(rtrim($m[2], '0'), '.') : $m[2];
    return $m[1] . 'x' . $spacing;
}

function password_problem(string $password): ?string
{
    if (mb_strlen($password) < 8) {
        return 'Hasło musi mieć co najmniej 8 znaków.';
    }
    if (mb_strlen($password) > 200) {
        return 'Hasło jest za długie.';
    }
    return null;
}
