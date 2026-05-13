<?php
// Script PHP standalone — jalankan sekali untuk sync accounts.json ke DB
// Letakkan di root project, jalankan: php sync-accounts.php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PangkalanSession;
use Illuminate\Support\Facades\Crypt;

$accountsFile = __DIR__ . '/scripts/accounts.json';

if (! file_exists($accountsFile)) {
    echo "ERROR: accounts.json tidak ditemukan di scripts/\n";
    exit(1);
}

$accounts = json_decode(file_get_contents($accountsFile), true);
if (! $accounts) {
    echo "ERROR: accounts.json tidak valid\n";
    exit(1);
}

echo "Memproses " . count($accounts) . " akun dari accounts.json...\n\n";

$updated = 0;
$skip    = 0;

foreach ($accounts as $acc) {
    $email = $acc['email'] ?? '';
    $pin   = $acc['pin']   ?? '';
    $label = $acc['label'] ?? $email;

    if (! $email || ! $pin) {
        echo "  SKIP: email atau pin kosong untuk {$label}\n";
        $skip++;
        continue;
    }

    $session = PangkalanSession::where('username', $email)->first();

    if (! $session) {
        // Buat baru jika belum ada
        PangkalanSession::create([
            'pangkalan_id'       => 'pending_' . md5($email . time()),
            'label'              => $label,
            'username'           => $email,
            'password_encrypted' => Crypt::encryptString($pin),
            'is_active'          => true,
        ]);
        echo "  BARU : {$label} ({$email})\n";
    } else {
        // Update password
        $session->update([
            'password_encrypted' => Crypt::encryptString($pin),
            'label'              => $session->label ?: $label,
        ]);
        echo "  UPDATE: {$label} ({$email}) — password diperbarui\n";
    }

    $updated++;
}

echo "\nSelesai: {$updated} akun diupdate/dibuat, {$skip} dilewati\n";
