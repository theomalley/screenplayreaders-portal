<?php

// v1.0 — 2026-11-13 | Structured-output shape for ContactInfoDetectionService's Claude call.

namespace App\Services;

use Anthropic\Lib\Attributes\Constrained;
use Anthropic\Lib\Concerns\StructuredOutputModelTrait;
use Anthropic\Lib\Contracts\StructuredOutputModel;

class ContactInfoVerdict implements StructuredOutputModel
{
    use StructuredOutputModelTrait;

    #[Constrained(description: 'True only if the transcript shows an actual exchange of personal contact information (phone number, personal email, social media handle) or an explicit agreement to communicate outside the platform. False for generic pleasantries, discussion of the script, or mention of the company\'s own support contact info.')]
    public bool $flagged;

    #[Constrained(description: 'Direct quotes from the transcript that support the verdict. Empty string if not flagged.')]
    public string $evidence;
}
