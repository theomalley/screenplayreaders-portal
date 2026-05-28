@props(['name' => 'timezone', 'selected' => 'UTC', 'required' => false])

@php
$timezones = [
    'UTC'                  => 'UTC',
    'America/New_York'     => 'Eastern — New York (UTC−5/−4)',
    'America/Chicago'      => 'Central — Chicago (UTC−6/−5)',
    'America/Denver'       => 'Mountain — Denver (UTC−7/−6)',
    'America/Phoenix'      => 'Mountain no-DST — Phoenix (UTC−7)',
    'America/Los_Angeles'  => 'Pacific — Los Angeles (UTC−8/−7)',
    'America/Anchorage'    => 'Alaska — Anchorage (UTC−9/−8)',
    'Pacific/Honolulu'     => 'Hawaii — Honolulu (UTC−10)',
    'Europe/London'        => 'London (UTC±0/+1)',
    'Europe/Paris'         => 'Paris / Berlin (UTC+1/+2)',
    'Asia/Dubai'           => 'Dubai (UTC+4)',
    'Asia/Kolkata'         => 'India — Kolkata (UTC+5:30)',
    'Asia/Singapore'       => 'Singapore (UTC+8)',
    'Asia/Tokyo'           => 'Tokyo (UTC+9)',
    'Australia/Sydney'     => 'Sydney (UTC+10/+11)',
    'Pacific/Auckland'     => 'Auckland (UTC+12/+13)',
];
@endphp

<select name="{{ $name }}" id="{{ $name }}"
    {{ $required ? 'required' : '' }}
    {{ $attributes->merge(['class' => 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm w-full']) }}>
    @foreach($timezones as $value => $label)
        <option value="{{ $value }}" {{ $selected === $value ? 'selected' : '' }}>{{ $label }}</option>
    @endforeach
</select>
