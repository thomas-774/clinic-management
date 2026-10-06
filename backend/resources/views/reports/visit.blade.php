{{--
    The visit report PDF (T10-03), laid out by mPDF from VisitReportData.
    Phones, amounts and drug names sit in dir="ltr" spans so they keep their
    order inside Arabic text (VR-4). The footer (page numbers) is set by
    PdfVisitReport. Section order matches the Word file (VR-3).
--}}
@php
    $l = $data->labels;
    $dir = fn (?string $text) => \App\Services\VisitReport\VisitReportData::textDir($text);
@endphp
<!DOCTYPE html>
<html lang="{{ $data->locale }}" dir="{{ $data->rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<title>{{ $data->title }}</title>
<style>
    body { font-family: {{ $font }}; font-size: 10.5pt; color: #000; line-height: 1.45; }
    /* Cairo's mark-on-mark lookups use mark filtering sets, which mPDF
       refuses ("MarkGlyphSets - Not tested yet") as soon as a text has
       harakat such as "جزئيًا". Single marks are still placed by "mark". */
    body, div, p, h1, h2, table, tr, td, th, span { font-feature-settings: "mkmk" off; }
    .start { text-align: {{ $data->rtl ? 'right' : 'left' }}; }
    .end { text-align: {{ $data->rtl ? 'left' : 'right' }}; }
    .center { text-align: center; }
    .header { border-bottom: 0.4mm solid #000; padding-bottom: 2mm; margin-bottom: 4mm; }
    .clinic { font-size: 16pt; font-weight: bold; }
    .doctor { font-size: 11.5pt; font-weight: bold; }
    .muted { font-size: 9.5pt; }
    h1 { font-size: 15pt; font-weight: bold; margin: 0 0 3mm; }
    h2 { font-size: 11.5pt; font-weight: bold; margin: 5mm 0 1.5mm; }
    table { border-collapse: collapse; width: 100%; }
    td, th { padding: 1.2mm 2mm; vertical-align: top; }
    .info td.label { font-weight: bold; width: 34mm; }
    .grid th { font-weight: bold; border-bottom: 0.3mm solid #000; }
    .grid td { border-bottom: 0.1mm solid #999; }
    .money td { border: 0.3mm solid #000; width: 33.3%; text-align: center; padding: 2.5mm 2mm; }
    .money .amount { font-size: 12.5pt; }
    .due { color: #c00000; }
    .bold { font-weight: bold; }
    .work { border: 0.1mm solid #999; padding: 2.5mm 3mm; }
    .signature td { padding-top: 14mm; }
</style>
</head>
<body>

{{-- Clinic header (as the prescription's) --}}
<div class="header center">
    @isset($data->header['clinic_name'])<div class="clinic" dir="{{ $dir($data->header['clinic_name']) }}">{{ $data->header['clinic_name'] }}</div>@endisset
    <div class="doctor" dir="{{ $dir($data->header['doctor_name'] ?? '') }}">{{ $data->header['doctor_name'] ?? '' }}@isset($data->header['doctor_title']) — {{ $data->header['doctor_title'] }}@endisset</div>
    @if (isset($data->header['address']) || isset($data->header['phone']))
        <div class="muted" dir="{{ $dir($data->header['address'] ?? '') }}">
            {{ $data->header['address'] ?? '' }}@if (isset($data->header['address'], $data->header['phone'])) · @endif
            @isset($data->header['phone'])<span dir="ltr">{{ $data->header['phone'] }}</span>@endisset
        </div>
    @endif
</div>

<h1 class="start">{{ $l['title'] }}</h1>

{{-- Patient and visit --}}
<table class="info">
    <tr>
        <td class="label start">{{ $l['patient'] }}</td>
        <td class="start" dir="{{ $dir($data->patient['name']) }}">{{ $data->patient['name'] }}</td>
        <td class="label start">{{ $l['visit_date'] }}</td>
        <td class="start">{{ $data->visit['date'] }}</td>
    </tr>
    <tr>
        <td class="label start">{{ $l['phone'] }}</td>
        <td class="start">@if ($data->patient['phone'])<span dir="ltr">{{ $data->patient['phone'] }}</span>@endif</td>
        <td class="label start">{{ $l['time'] }}</td>
        <td class="start">@if ($data->visit['walk_in']){{ $l['walk_in'] }}@else<span dir="ltr">{{ $data->visit['time'] }}</span>@endif</td>
    </tr>
    <tr>
        <td class="label start">@if ($data->patient['age'] !== null){{ $l['age'] }}@endif</td>
        <td class="start">{{ $data->patient['age'] }}</td>
        <td class="label start">{{ $l['payment_status'] }}</td>
        <td class="start">{{ $data->visit['payment_status'] }}</td>
    </tr>
</table>

{{-- Work done today --}}
<h2 class="start">{{ $l['work_done'] }}</h2>
{{-- Each line keeps its own direction (a note may mix Arabic and English lines). --}}
<div class="work start">
    @foreach (preg_split('/\R/u', $data->workDone) as $line)
        <div dir="{{ $dir($line) }}" class="start">{{ $line }}&nbsp;</div>
    @endforeach
</div>

{{-- Money --}}
<h2 class="start">{{ $l['money'] }}</h2>
<table class="money">
    <tr>
        <td>{{ $l['total'] }}<br><span class="amount" dir="ltr">{{ $data->money['total'] }}</span></td>
        <td>{{ $l['paid'] }}<br><span class="amount" dir="ltr">{{ $data->money['paid'] }}</span></td>
        <td class="bold{{ $data->money['has_remaining'] ? ' due' : '' }}">{{ $l['remaining'] }}<br><span class="amount" dir="ltr">{{ $data->money['remaining'] }}</span></td>
    </tr>
</table>

{{-- Payments of this visit --}}
<h2 class="start">{{ $l['payments'] }}</h2>
@if ($data->payments === [])
    <p class="start">{{ $l['no_payments'] }}</p>
@else
    <table class="grid" repeat_header="1">
        <thead>
            <tr>
                <th class="start">{{ $l['date'] }}</th>
                <th class="start">{{ $l['amount'] }}</th>
                <th class="start">{{ $l['method'] }}</th>
                <th class="start">{{ $l['recorded_by'] }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data->payments as $payment)
                <tr style="page-break-inside: avoid">
                    <td class="start">{{ $payment['date'] }}</td>
                    <td class="start"><span dir="ltr">{{ $payment['amount'] }}</span></td>
                    <td class="start">{{ $payment['method'] }}</td>
                    <td class="start" dir="{{ $dir($payment['recorded_by']) }}">{{ $payment['recorded_by'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Overall outstanding (FR-D.7) --}}
<p class="start bold" style="margin-top: 4mm">
    {{ $l['outstanding'] }}:
    <span dir="ltr" class="{{ $data->hasOutstanding ? 'due' : '' }}">{{ $data->outstanding }}</span>
</p>

{{-- Prescriptions of this visit (only when there are any) --}}
@if ($data->prescriptions !== [])
    <h2 class="start">{{ $l['prescriptions'] }}</h2>
    @foreach ($data->prescriptions as $prescription)
        <p class="start muted" style="margin: 1mm 0">{{ $l['date'] }}: {{ $prescription['issued_on'] }}</p>
        <table class="grid" repeat_header="1">
            <thead>
                <tr>
                    <th class="start" style="width: 8mm">#</th>
                    <th class="start">{{ $l['drug'] }}</th>
                    <th class="start">{{ $l['form'] }}</th>
                    <th class="start">{{ $l['instructions'] }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($prescription['items'] as $item)
                    <tr style="page-break-inside: avoid">
                        <td class="start">{{ $loop->iteration }}</td>
                        <td class="start"><span dir="ltr">{{ $item['drug_name'] }}</span></td>
                        <td class="start" dir="{{ $dir($item['drug_form']) }}">{{ $item['drug_form'] }}</td>
                        <td class="start" dir="{{ $dir($item['instructions']) }}">{{ $item['instructions'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
@endif

{{-- Generated on, signature --}}
<table class="signature">
    <tr>
        <td class="start muted">{{ $l['generated_on'] }}: {{ $data->generatedAt }}</td>
        <td class="end">{{ $l['signature'] }}: ______________________</td>
    </tr>
</table>

</body>
</html>
