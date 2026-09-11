<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Один пользователь для входа в приложение: регистрации в задании нет.
     *
     * Логин и пароль печатаются после сидинга, чтобы их не пришлось искать
     * в README.
     */
    public function run(): void
    {
        $email = (string) env('SEED_USER_EMAIL', 'test@example.com');
        $password = (string) env('SEED_USER_PASSWORD', 'password');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Тестовый пользователь',
                'password' => $password,
            ],
        );

        $this->command->info("Пользователь для входа: {$email} / {$password}");
    }
}
