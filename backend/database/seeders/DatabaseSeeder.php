<?php

namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = $this->user([
            'full_name' => 'Administrador SecureWallet',
            'ci' => '1000001',
            'email' => 'admin@securewallet.test',
            'phone' => '70000001',
            'role' => 'ADMIN',
            'password' => 'Admin123*Secure',
        ], 0);

        $userA = $this->user([
            'full_name' => 'Manuel SecureWallet',
            'ci' => '1000002',
            'email' => 'manuel123@gmail.com',
            'phone' => '70000002',
            'role' => 'USER',
            'password' => 'Manuel_123',
        ], 200000);

        $userB = $this->user([
            'full_name' => 'Carmen SecureWallet',
            'ci' => '1000003',
            'email' => 'carmen123@gmail.com',
            'phone' => '70000003',
            'role' => 'USER',
            'password' => 'Carmen_123',
            'mfa_enabled' => false,
            'mfa_secret' => null,
        ], 100000);

        unset($admin, $userA, $userB);
    }

    private function user(array $data, int $initialBalanceCents): User
    {
        $password = $data['password'];
        unset($data['password']);

        /** @var User $user */
        $user = User::query()->updateOrCreate(
            ['email' => $data['email']],
            array_merge($data, [
                'password' => Hash::make($password, ['rounds' => config('securewallet.bcrypt_rounds', 12)]),
                'failed_login_attempts' => 0,
                'is_blocked' => false,
                'blocked_until' => null,
            ])
        );

        /** @var Wallet $wallet */
        $wallet = Wallet::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['balance_cents' => $initialBalanceCents]
        );

        if ($initialBalanceCents > 0) {
            Transaction::query()->firstOrCreate([
                'user_id' => $user->id,
                'type' => 'RECARGA',
                'description' => 'Saldo inicial de demostración',
            ], [
                'amount_cents' => $initialBalanceCents,
                'balance_after_cents' => $wallet->balance_cents,
                'created_at' => now(),
            ]);
        }

        return $user;
    }
}
