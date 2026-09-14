<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Expense::query()
            ->with('category')
            ->orderByDesc('date')
            ->orderByDesc('id');

        // A malformed month simply matches nothing (date LIKE '2026-06%'); not an error.
        if ($request->filled('month')) {
            $query->inMonth($request->query('month'));
        }

        // 0 / empty / non-numeric means "no filter"; an unknown id just returns empty.
        $categoryId = (int) $request->query('category_id');
        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }

        $defaultPerPage = (int) config('pesonal.pagination.default_per_page');
        $maxPerPage = (int) config('pesonal.pagination.max_per_page');

        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) $request->query('per_page', $defaultPerPage);
        $perPage = $perPage < 1 ? $defaultPerPage : min($perPage, $maxPerPage);

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        return response()->json([
            'data' => ExpenseResource::collection($paginator->getCollection()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(), // a row count, never a sum of amount
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $expense = Expense::create([
            'amount' => $validated['amount'],
            'date' => $validated['date'],
            'category_id' => $validated['category_id'],
            'note' => $request->input('note'), // recurring_id stays null — never from the body
        ]);

        return ExpenseResource::make($expense->load('category'))->response()->setStatusCode(201);
    }

    public function show(Expense $expense): ExpenseResource
    {
        return ExpenseResource::make($expense->load('category'));
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): ExpenseResource
    {
        $validated = $request->validated();

        // Only sent fields change.
        if (array_key_exists('amount', $validated)) {
            $expense->amount = $validated['amount'];
        }
        if (array_key_exists('date', $validated)) {
            $expense->date = $validated['date'];
        }
        if (array_key_exists('category_id', $validated)) {
            $expense->category_id = $validated['category_id'];
        }
        // exists() is true even when note is an explicit null — that clears it, and
        // must be distinguished from an absent key (which leaves the note untouched).
        if ($request->exists('note')) {
            $expense->note = $request->input('note');
        }

        $expense->save();

        return ExpenseResource::make($expense->load('category'));
    }

    public function destroy(Expense $expense): Response
    {
        $expense->delete();

        return response()->noContent();
    }
}
