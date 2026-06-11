{{-- 5-minute increment <option> list for time <select> elements (00:00–23:55) --}}
<option value="">Time</option>
@for ($m = 0; $m < 1440; $m += 5)
    @php
        $h24 = intdiv($m, 60);
        $mi  = $m % 60;
        $val = sprintf('%02d:%02d', $h24, $mi);
    @endphp
    <option value="{{ $val }}">{{ \Carbon\Carbon::createFromTime($h24, $mi)->format('g:i A') }}</option>
@endfor
