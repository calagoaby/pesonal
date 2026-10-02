<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecurringRequest;
use App\Http\Requests\UpdateRecurringRequest;
use App\Http\Resources\RecurringResource;
use App\Models\Recurring;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class RecurringController extends Controller
{
    // Whole set, unpaginated, no meta (like categories) — the screen sums a monthly total.
    public function index(): AnonymousResourceCollection
    {
        return RecurringResource::collection(
            Recurring::with('category')->orderBy('id')->get()
        );
    }

    public function store(StoreRecurringRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $recurring = Recurring::create([
            'amount' => $validated['amount'],
            'category_id' => $validated['category_id'],
            'day_of_month' => $validated['day_of_month'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'note' => $request->input('note'), // note ?? null
            'active' => $request->input('active') ?? true, // active ?? true
        ]);

        return RecurringResource::make($recurring->load('category'))->response()->setStatusCode(201);
    }

    public function show(Recurring $recurring): RecurringResource
    {
        return RecurringResource::make($recurring->load('category'));
    }

    public function update(UpdateRecurringRequest $request, Recurring $recurring): RecurringResource
    {
        $validated = $request->validated();

        foreach (['amount', 'category_id', 'day_of_month', 'start_date'] as $field) {
            if (array_key_exists($field, $validated)) {
                $recurring->$field = $validated[$field];
            }
        }

        // Present (even null) overwrites — null clears the end date to open-ended.
        if (array_key_exists('end_date', $validated)) {
            $recurring->end_date = $validated['end_date'];
        }

        // Present (even null) overwrites; absent leaves the note unchanged.
        if ($request->exists('note')) {
            $recurring->note = $request->input('note');
        }

        // active ?? existing: an explicit boolean toggles; null or absent leaves it unchanged.
        if ($request->input('active') !== null) {
            $recurring->active = (bool) $request->input('active');
        }

        $recurring->save();

        return RecurringResource::make($recurring->load('category'));
    }

    public function destroy(Recurring $recurring): Response
    {
        // Only the template goes; its materialized expenses keep their recurring_id.
        $recurring->delete();

        return response()->noContent();
    }
}
