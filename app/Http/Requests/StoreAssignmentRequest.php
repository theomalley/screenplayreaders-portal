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
            'assignment_type'      => ['nullable', 'string'],
            'script_title'         => ['required', 'string', 'max:255'],
            'author_first_initial' => ['required', 'string', 'size:1'],
            'author_last_name'     => ['required', 'string', 'max:100'],
            'page_count'           => ['required', 'integer', 'min:1', 'max:9999'],
            'requested_reader_id'  => ['nullable', 'exists:users,id'],
            'rush'                 => ['boolean'],
            'pay_rate'             => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'notes'                => ['nullable', 'string', 'max:2000'],
            'status'               => ['required', 'in:' . Assignment::STATUS_INCOMING . ',' . Assignment::STATUS_UNASSIGNED],
        ];
    }
}
