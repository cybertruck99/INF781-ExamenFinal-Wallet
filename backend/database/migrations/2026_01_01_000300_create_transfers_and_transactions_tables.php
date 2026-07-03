<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->bigInteger('amount_cents');
            $table->string('description', 180)->nullable();
            $table->string('status', 40)->default('PENDIENTE_CONFIRMACION')->index();
            $table->boolean('requires_totp')->default(false);
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['sender_user_id', 'status']);
            $table->index(['recipient_user_id', 'status']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transfer_id')->nullable()->constrained('transfers')->nullOnDelete();
            $table->string('type', 20)->index();
            $table->bigInteger('amount_cents');
            $table->foreignId('counterparty_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('description', 180)->nullable();
            $table->bigInteger('balance_after_cents');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE transfers ADD CONSTRAINT transfers_amount_positive CHECK (amount_cents > 0)');
            DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_amount_positive CHECK (amount_cents > 0)');
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_valid CHECK (type IN ('RECARGA', 'ENVIO', 'RECEPCION'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('transfers');
    }
};
