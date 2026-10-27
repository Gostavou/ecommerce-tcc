<?php

namespace App\Http\Controllers;

use App\Models\Endereco;
use App\Models\Pedido;
use App\Models\Produto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PedidoController extends Controller
{
    public function index(Request $request)
    {
        $pedidos = Pedido::with(['itens.produto', 'endereco'])
                    ->where('user_id', $request->user()->id)
                    ->latest()
                    ->get();

        return response()->json($pedidos);
    }


    public function show(Request $request, string $pedido)
    {
        $pedidoEncontrado = Pedido::with(['itens.produto', 'endereco'])
                    ->where('user_id', $request->user()->id)
                    ->where('id', $pedido)
                    ->firstOrFail();

        return response()->json($pedidoEncontrado);
    }

    public function adminIndex()
    {
        $pedidos = Pedido::with([
            'user:id,name,email',
            'itens.produto',
        ])
            ->latest()
            ->get();

        return response()->json($pedidos);
    }

    public function adminShow(string $pedido)
    {
        $pedidoEncontrado = Pedido::with([
            'user:id,name,email',
            'itens.produto',
        ])->findOrFail($pedido);

        return response()->json($pedidoEncontrado);
    }


    public function atualizarStatus(Request $request, string $pedido)
    {
        $dados = $request->validate([
            'status' => [
                'required',
                'string',
                'in:confirmado,processando,enviado,entregue',
            ],
        ]);

        $pedidoAtualizado = DB::transaction(function () use (
            $pedido,
            $dados
        ) {
            $pedidoEncontrado = Pedido::query()
                ->lockForUpdate()
                ->findOrFail($pedido);

            $transicoesPermitidas = [
                'pendente' => ['confirmado'],
                'confirmado' => ['processando'],
                'processando' => ['enviado'],
                'enviado' => ['entregue'],
                'entregue' => [],
                'cancelado' => [],
            ];

            $statusAtual = $pedidoEncontrado->status;
            $novoStatus = $dados['status'];

            if ($statusAtual !== $novoStatus) {
                $permitidos = $transicoesPermitidas[$statusAtual] ?? [];

                if (!in_array($novoStatus, $permitidos, true)) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Esta mudança de status não é permitida. '
                            . 'O pedido deve seguir a próxima etapa do fluxo.',
                        ],
                    ]);
                }

                $pedidoEncontrado->status = $novoStatus;
                $pedidoEncontrado->save();
            }

            return $pedidoEncontrado->load([
                'user:id,name,email',
                'itens.produto',
            ]);
        });

        return response()->json([
            'message' => 'Status do pedido atualizado com sucesso.',
            'pedido' => $pedidoAtualizado,
        ]);
    }

    public function store(Request $request)
    {
        $dados = $request->validate([
            'endereco_id' => [
                'required',
                'integer',
                'exists:enderecos,id',
            ],

            'forma_pagamento' => [
                'required',
                'in:pix,cartao_credito',
            ],

            'itens' => [
                'required',
                'array',
                'min:1',
            ],

            'itens.*.produto_id' => [
                'required',
                'integer',
                'distinct',
                'exists:produtos,id',
            ],

            'itens.*.quantidade' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        $pedido = DB::transaction(function () use ($request, $dados) {
            $usuario = $request->user();

            $endereco = Endereco::query()
                ->where('user_id', $usuario->id)
                ->whereKey($dados['endereco_id'])
                ->lockForUpdate()
                ->first();

            if (!$endereco) {
                throw ValidationException::withMessages([
                    'endereco_id' => [
                        'O endereço selecionado não pertence à sua conta.',
                    ],
                ]);
            }

            $produtosDoPedido = [];
            $subtotalCentavos = 0;

            foreach ($dados['itens'] as $item) {
                $produto = Produto::query()
                    ->lockForUpdate()
                    ->find($item['produto_id']);

                if (!$produto) {
                    throw ValidationException::withMessages([
                        'itens' => [
                            'Um dos produtos não foi encontrado.',
                        ],
                    ]);
                }

                $quantidade = (int) $item['quantidade'];
                $estoque = (int) $produto->estoque;

                if ($estoque < $quantidade) {
                    throw ValidationException::withMessages([
                        'itens' => [
                            "Estoque insuficiente para {$produto->nome}. "
                            . "Disponíveis: {$estoque}.",
                        ],
                    ]);
                }

                $precoCentavos = (int) round(
                    (float) $produto->preco * 100
                );

                $subtotalItem = $precoCentavos * $quantidade;
                $subtotalCentavos += $subtotalItem;

                $produtosDoPedido[] = [
                    'produto' => $produto,
                    'quantidade' => $quantidade,
                    'preco_centavos' => $precoCentavos,
                    'subtotal_centavos' => $subtotalItem,
                ];
            }

            $freteCentavos = $this->calcularFrete(
                $subtotalCentavos
            );

            $pedido = Pedido::create([
                'user_id' => $usuario->id,
                'endereco_id' => $endereco->id,

                'endereco_nome' => $endereco->nome,
                'cep' => $endereco->cep,
                'rua' => $endereco->rua,
                'numero' => $endereco->numero,
                'complemento' => $endereco->complemento,
                'bairro' => $endereco->bairro,
                'cidade' => $endereco->cidade,
                'estado' => $endereco->estado,

                'status' => 'confirmado',
                'forma_pagamento' => $dados['forma_pagamento'],
                'status_pagamento' => 'aprovado_simulado',

                'subtotal' => $this->formatarCentavos(
                    $subtotalCentavos
                ),

                'frete' => $this->formatarCentavos(
                    $freteCentavos
                ),

                'total' => $this->formatarCentavos(
                    $subtotalCentavos + $freteCentavos
                ),
            ]);

            foreach ($produtosDoPedido as $item) {
                $produto = $item['produto'];
                $quantidade = $item['quantidade'];

                $pedido->itens()->create([
                    'produto_id' => $produto->id,
                    'nome_produto' => $produto->nome,

                    'preco_unitario' => $this->formatarCentavos(
                        $item['preco_centavos']
                    ),

                    'quantidade' => $quantidade,

                    'subtotal' => $this->formatarCentavos(
                        $item['subtotal_centavos']
                    ),
                ]);

                $produto->decrement('estoque', $quantidade);
            }

            return $pedido;
        });

        return response()->json([
            'message' => 'Pedido criado e pagamento aprovado.',
            'pedido' => $pedido->load(['itens.produto', 'endereco']),
        ], 201);
    }


    private function calcularFrete(int $subtotalCentavos): int
    {
        if ($subtotalCentavos >= 50000) {
            return 0;
        }

        if ($subtotalCentavos >= 25000) {
            return 990;
        }

        if ($subtotalCentavos >= 10000) {
            return 1490;
        }

        return 1990;
    }

    private function formatarCentavos(int $valor): string
    {
        return number_format($valor / 100, 2, '.', '');
    }
}
