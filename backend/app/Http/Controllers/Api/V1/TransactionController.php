<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsResponses;
use App\Http\Controllers\Api\V1\Concerns\ValidatesQueryParameters;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    use FormatsResponses, ValidatesQueryParameters;

    public function index(Request $request): JsonResponse
    {
        $data = $this->validateQuery($request, [
            'type' => ['nullable', 'string', Rule::in(['RECARGA', 'ENVIO', 'RECEPCION'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Transaction::query()
            ->with('counterparty')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at');

        if (! empty($data['type'])) {
            $query->where('type', $data['type']);
        }

        $page = $query->paginate($data['per_page'] ?? 10);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Transaction $tx) => $this->transactionPayload($tx))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
