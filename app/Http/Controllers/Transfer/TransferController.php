<?php

namespace App\Http\Controllers\Transfer;

use App\Enums\RejectionReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transfer\ForwardTransferRequest;
use App\Http\Requests\Transfer\StoreTransferRequest;
use App\Models\Examination;
use App\Models\Transfer;
use App\Services\TransferLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class TransferController extends Controller
{
    public function __construct(private readonly TransferLifecycleService $lifecycle)
    {
        //
    }

    public function store(StoreTransferRequest $request, Examination $examination): RedirectResponse
    {
        $transfer = $this->lifecycle->send(
            examination: $examination,
            sender: $request->user(),
            toDepartmentIds: $request->validated('department_ids'),
            message: $request->validated('message'),
        );

        return redirect()
            ->route('examinations.show', $examination)
            ->with('status', "Transfer sent to {$transfer->recipients->count()} department(s).");
    }

    public function forward(ForwardTransferRequest $request, Transfer $transfer): RedirectResponse
    {
        $forwarded = $this->lifecycle->forward(
            original: $transfer,
            sender: $request->user(),
            toDepartmentIds: $request->validated('department_ids'),
            message: $request->validated('message'),
        );

        return redirect()
            ->route('examinations.show', $transfer->examination_id)
            ->with('status', "Transfer forwarded to {$forwarded->recipients->count()} department(s).");
    }

    public function acknowledge(Request $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('acknowledge', $transfer);

        $recipient = $transfer->recipients()
            ->where('department_id', $request->user()->department_id)
            ->firstOrFail();

        $this->lifecycle->acknowledge($recipient, $request->user());

        return back()->with('status', 'Transfer receipt acknowledged.');
    }

    public function complete(Request $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('complete', $transfer);

        $recipient = $transfer->recipients()
            ->where('department_id', $request->user()->department_id)
            ->firstOrFail();

        try {
            $this->lifecycle->complete($recipient);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['transfer' => $exception->getMessage()]);
        }

        return back()->with('status', 'Transfer marked complete.');
    }

    public function reject(Request $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('reject', $transfer);

        $request->validate([
            'reason' => ['required', Rule::enum(RejectionReason::class)],
        ]);

        $recipient = $transfer->recipients()
            ->where('department_id', $request->user()->department_id)
            ->firstOrFail();

        try {
            $this->lifecycle->reject($recipient, $request->user(), $request->input('reason'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['transfer' => $exception->getMessage()]);
        }

        return back()->with('status', 'Transfer rejected.');
    }

    public function recall(Request $request, Transfer $transfer): RedirectResponse
    {
        $this->authorize('recall', $transfer);

        $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
        ]);

        $recipient = $transfer->recipients()
            ->where('department_id', $request->integer('department_id'))
            ->firstOrFail();

        try {
            $this->lifecycle->recall($recipient, $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['transfer' => $exception->getMessage()]);
        }

        return back()->with('status', 'Transfer recalled.');
    }
}
