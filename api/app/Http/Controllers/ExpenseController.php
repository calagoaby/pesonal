<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtered = Expense::query();

        // A malformed month simply matches nothing (see Expense::scopeInMonth); not an error.
        if ($request->filled('month')) {
            $filtered->inMonth($request->query('month'));
        }

        // 0 / empty / non-numeric means "no filter"; an unknown id just returns empty.
        $categoryId = (int) $request->query('category_id');
        if ($categoryId > 0) {
            $filtered->where('category_id', $categoryId);
        }

        $defaultPerPage = (int) config('pesonal.pagination.default_per_page');
        $maxPerPage = (int) config('pesonal.pagination.max_per_page');

        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) $request->query('per_page', $defaultPerPage);
        $perPage = $perPage < 1 ? $defaultPerPage : min($perPage, $maxPerPage);

        $paginator = (clone $filtered)
            ->with('category')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page);

        return response()->json([
            'data' => ExpenseResource::collection($paginator->getCollection()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(), // a row count, never a sum of amount
                'last_page' => $paginator->lastPage(),
                'day_totals' => $this->dayTotals($filtered, $paginator->getCollection()),
            ],
        ]);
    }

    /**
     * Whole-day totals for the dates on this page, under the same filters, so a day
     * split across pages reports the same figure on both. Summed here, not in SQL:
     * amount is ciphertext.
     *
     * @param  Builder<Expense>  $filtered
     * @param  Collection<int, Expense>  $page
     */
    private function dayTotals(Builder $filtered, Collection $page): object
    {
        $dates = $page->map(fn (Expense $row) => $row->date->format('Y-m-d'))->unique()->values();
        $totals = [];

        if ($dates->isNotEmpty()) {
            $rows = (clone $filtered)->whereIn('date', $dates->all())->get(['date', 'amount']);

            foreach ($rows as $row) {
                $date = $row->date->format('Y-m-d');
                $totals[$date] = ($totals[$date] ?? 0) + $row->amount->centavos();
            }
        }

        // Page order (newest first), and an object even when empty: [] would encode as a list.
        return (object) $dates->mapWithKeys(fn (string $date) => [$date => $totals[$date] ?? 0])->all();
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $expense = Expense::create([
            'amount' => $validated['amount'],
            'date' => $validated['date'],
            'category_id' => $validated['category_id'],
            'note' => $request->input('note'), // recurring_id stays null — never from the body
            'items' => array_values($validated['items'] ?? []),
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

        // Absent leaves the list alone; [] clears it; a list replaces it.
        if (array_key_exists('items', $validated)) {
            $expense->items = array_values($validated['items']);
        }

        $expense->save();

        return ExpenseResource::make($expense->load('category'));
    }

    public function destroy(Expense $expense): Response
    {
        $expense->delete();

        return response()->noContent();
    }

    // Undo of a void: the same row comes back, recurring_id and all.
    public function restore(Expense $voidedExpense): ExpenseResource
    {
        $voidedExpense->restore();

        return ExpenseResource::make($voidedExpense->load('category'));
    }
}
