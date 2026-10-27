<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\EnderecoController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\AdminUsuarioController;

// Produtos: consulta pública
Route::get('/produtos', [ProdutoController::class, 'index']);
Route::get('/produtos/{produto}', [ProdutoController::class, 'show']);

// Produtos: gerenciamento exclusivo do administrador
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Produtos
    Route::post('/produtos', [ProdutoController::class, 'store']);
    Route::put('/produtos/{produto}', [ProdutoController::class, 'update']);
    Route::patch('/produtos/{produto}', [ProdutoController::class, 'update']);
    Route::delete('/produtos/{produto}', [ProdutoController::class, 'destroy']);

    // Pedidos: acesso exclusivo do administrador
    Route::get('/admin/pedidos', [PedidoController::class, 'adminIndex']);
    Route::get('/admin/pedidos/{pedido}', [PedidoController::class, 'adminShow']);
    Route::patch('/admin/pedidos/{pedido}/status', [PedidoController::class, 'atualizarStatus']);

    // Usuários: acesso exclusivo do administrador
    Route::get('/admin/usuarios', [AdminUsuarioController::class, 'index']);
    Route::patch('/admin/usuarios/{usuario}', [AdminUsuarioController::class, 'update']);
    Route::delete('/admin/usuarios/{usuario}', [AdminUsuarioController::class, 'destroy']);
});

// Autenticação: cadastro e login
Route::post('/registrar', [AuthController::class, 'registrar']);
Route::post('/login', [AuthController::class, 'login']);

// Perfil do usuário autenticado
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/perfil', [PerfilController::class, 'show']);
    Route::put('/perfil', [PerfilController::class, 'update']);
});

// Endereços do usuário autenticado
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('enderecos', EnderecoController::class);
});

// Pedidos do usuário autenticado
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/pedidos', [PedidoController::class, 'index']);
    Route::get('/pedidos/{pedido}', [PedidoController::class, 'show']);
    Route::post('/pedidos', [PedidoController::class, 'store']);
});
