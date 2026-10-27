<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('endereco_id')
                ->nullable()
                ->constrained('enderecos')
                ->nullOnDelete();

            // Cópia dos dados do endereço no momento da compra.
            $table->string('endereco_nome');
            $table->string('cep', 9);
            $table->string('rua');
            $table->string('numero', 20);
            $table->string('complemento')->nullable();
            $table->string('bairro');
            $table->string('cidade');
            $table->char('estado', 2);

            $table->string('status')->default('pendente');

            $table->decimal('subtotal', 10, 2);
            $table->decimal('frete', 10, 2)->default(0);
            $table->decimal('total', 10, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
