<?php

namespace App\Http\Requests;

use App\Models\Assignment;
use Illuminate\Foundation\Http\FormRequest;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canManageAssignments();
    }

    public function rules(): array
    {
        return [
            'order_number'         => ['required', 'string', 'max:50'],
            'vendor'               => ['required', 'in:sr,wd'],
            'num_readers'          => ['required', 'in:1,2,3'],
            'assignment_type'      => ['nullable', 'in:script_coverage,notes_only,deep_dive,short,budget,book,coverage,development_notes,formatting,proofreading'],
            'script_title'    => ['required', 'string', 'max:255'],
            'writer_name'     => ['required', 'string', 'max:255'],
            'page_count'      => ['required', 'integer', 'min:1', 'max:9999'],
            'requested_reader_id_1' => ['nullable', 'exists:users,id'],
            'requested_reader_id_2' => ['nullable', 'exists:users,id'],
            'requested_reader_id_3' => ['nullable', 'exists:users,id'],
            'assigned_reader_id'    => ['nullable', 'exists:users,id'],
            'blocked_reader_ids'    => ['nullable', 'array'],
            'blocked_reader_ids.*'  => ['integer', 'exists:users,id'],
            'rush'                 => ['nullable', 'boolean'],
            'proofreading'         => ['nullable', 'boolean'],
            'pay_rate'             => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'notes'                    => ['nullable', 'string', 'max:2000'],
            'helpscout_ticket_number'  => ['nullable', 'string', 'max:64'],
            'date'                 => ['nullable', 'date_format:Y-m-d'],
            'time'                 => ['nullable', 'date_format:H:i'],
            'exempt_from_word_counts' => ['nullable', 'boolean'],
            'oversized_fee_included'  => ['nullable', 'boolean'],
            'manual_page_flag'        => ['nullable', 'in:over_120,over_160'],
            'tiers'                => ['nullable', 'array'],
            'tiers.*'              => ['integer', 'exists:tiers,id'],
            'script'               => ['nullable', 'file', 'mimes:pdf', 'max:51200'],
            'status'               => ['required', 'in:' . implode(',', [
                                          Assignment::STATUS_INCOMING,
                                          Assignment::STATUS_UNASSIGNED,
                                          Assignment::STATUS_ASSIGNED,
                                          Assignment::STATUS_QC,
                                          Assignment::STATUS_COMPLETED,
                                          Assignment::STATUS_ON_HOLD_CUSTOMER,
                                          Assignment::STATUS_ON_HOLD_SR,
                                          Assignment::STATUS_CANCELLED,
                                      ]),],
        ];
    }
}
