@php
    $initialSegments = old('segments', $segments ?? [
        ['type' => 'literal', 'value' => 'DOC-'],
        ['type' => 'token', 'value' => 'YYYY'],
        ['type' => 'sequence', 'width' => 4],
    ]);
    if (is_string($initialSegments)) $initialSegments = json_decode($initialSegments, true) ?: [];
@endphp
<div x-data='documentTypeBuilder(@json($initialSegments))' data-preview-url="{{ route('document-types.preview') }}">
    <input type="hidden" name="segments" :value="JSON.stringify(segments)">
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label" for="code">Kode</label><input id="code" name="code" class="form-control" maxlength="50" required value="{{ old('code', $documentType->code ?? '') }}" placeholder="QUOTATION"><div class="form-hint">Huruf kapital, angka, underscore, atau hyphen.</div></div>
        <div class="col-md-5"><label class="form-label" for="name">Nama</label><input id="name" name="name" class="form-control" maxlength="150" required value="{{ old('name', $documentType->name ?? '') }}" placeholder="Quotation"></div>
        <div class="col-md-3"><label class="form-label" for="approval_mode">Mode approval</label><select id="approval_mode" name="approval_mode" class="form-select"><option value="direct" @selected(old('approval_mode', $documentType->approval_mode ?? 'direct') === 'direct')>Direct</option><option value="maker_checker" @selected(old('approval_mode', $documentType->approval_mode ?? '') === 'maker_checker')>Maker-checker</option></select></div>
    </div>
    <div class="row g-3 mt-1">
        <div class="col-md-4">
            <label class="form-label" for="latest_sequence">Latest sequence {{ $sequencePeriodYear ?? now(config('office.business_timezone'))->year }}</label>
            <input id="latest_sequence" name="latest_sequence" type="number" min="0" step="1" class="form-control" required value="{{ old('latest_sequence', $latestSequence ?? 0) }}">
            <div class="form-hint">Request nomor berikutnya memakai nilai ini + 1. Nilai yang sudah tersimpan hanya dapat dinaikkan.</div>
            @error('latest_sequence')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="mt-4"><div class="d-flex align-items-center mb-2"><label class="form-label mb-0">Segment builder</label><div class="ms-auto btn-list"><button type="button" class="btn btn-sm" @click="add('literal')">+ Literal</button><button type="button" class="btn btn-sm" @click="add('token')">+ Token tanggal</button><button type="button" class="btn btn-sm" @click="add('sequence')">+ Sequence</button></div></div>
        <div class="list-group">
            <template x-for="(segment, index) in segments" :key="index">
                <div class="list-group-item"><div class="row g-2 align-items-center">
                    <div class="col-md-3"><select class="form-select" x-model="segment.type"><option value="literal">Literal</option><option value="token">Token tanggal</option><option value="sequence">Sequence</option></select></div>
                    <div class="col"><input x-show="segment.type === 'literal'" x-model="segment.value" class="form-control" maxlength="80" placeholder="QT-JBLU-"><select x-show="segment.type === 'token'" x-model="segment.value" class="form-select"><option value="YYYY">YYYY</option><option value="YY">YY</option><option value="MM">MM</option><option value="MONTH_ROMAN">MONTH_ROMAN</option></select><input x-show="segment.type === 'sequence'" x-model.number="segment.width" type="number" min="1" max="10" class="form-control" aria-label="Lebar sequence"></div>
                    <div class="col-auto"><button type="button" class="btn btn-outline-danger" @click="remove(index)" aria-label="Hapus segmen">Hapus</button></div>
                </div></div>
            </template>
        </div>
        <div class="form-hint mt-2">Pola wajib memiliki tepat satu sequence. Reset sequence tetap tahunan walaupun token tahun tidak ditampilkan.</div>
    </div>
    <div class="card bg-light mt-4"><div class="card-body"><div class="text-secondary">Pola tersimpan</div><code x-text="pattern || '-'">-</code><div class="text-secondary mt-3">Preview nomor pertama</div><div class="h2 mb-0" x-text="preview || '-'">-</div><div x-show="error" class="text-danger mt-2" x-text="error"></div></div></div>
    <div class="mt-4"><button class="btn btn-primary" type="submit">Simpan</button><a href="{{ route('document-types.index') }}" class="btn">Batal</a></div>
</div>
