@extends('layouts.app')

@section('title', 'My Personal Cardiology Order Sets')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-bookmark-star-fill me-2 text-success"></i>My Personal Favorite Order Sets &amp; Hospital Protocols</h1>
        <small class="text-muted">Save your most-used cardiology regimens once — apply them in 1 click during Rapid 60-Second Consults</small>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-6">
        <div class="hmis-card p-3 mb-4">
            <h2 class="h6 fw-bold border-bottom pb-2 mb-3"><i class="bi bi-plus-circle me-1 text-primary"></i>Create Personal 1-Click Favorite Bundle</h2>
            <form method="POST" action="{{ route('emr.favorites.store') }}">
                @csrf
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Bundle Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control form-control-sm" required placeholder="e.g. Dr. Tariq Post-CABG Routine Follow-Up">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Short Description</label>
                    <input type="text" name="description" class="form-control form-control-sm" placeholder="When to use this favorite">
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Default NYHA</label>
                        <select name="default_nyha_class" class="form-select form-select-sm">
                            <option value="">None</option>
                            <option value="I">Class I</option>
                            <option value="II">Class II</option>
                            <option value="III">Class III</option>
                            <option value="IV">Class IV</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Default Duration (Days)</label>
                        <input type="number" name="default_duration_days" value="30" min="1" max="90" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Default Exam / Assessment / Plan</label>
                    <input type="text" name="default_exam_text" class="form-control form-control-sm mb-1" placeholder="Exam (e.g. S1+S2+0, chest clear)">
                    <input type="text" name="default_assessment_text" class="form-control form-control-sm mb-1" placeholder="Assessment">
                    <input type="text" name="default_plan_text" class="form-control form-control-sm mb-1" placeholder="Plan">
                    <input type="text" name="default_follow_up" class="form-control form-control-sm" placeholder="Follow-up (e.g. 4 weeks with Lipid Profile)">
                </div>

                <div class="mb-2">
                    <label class="form-label small fw-semibold">Medications to Pre-Fill</label>
                    <div class="border rounded p-2" style="max-height: 120px; overflow-y: auto;">
                        @foreach($medications as $m)
                            <div class="form-check small">
                                <input class="form-check-input" type="checkbox" name="medication_codes[]" value="{{ $m->code }}" id="fmed_{{ $m->id }}">
                                <label class="form-check-label" for="fmed_{{ $m->id }}"><strong>{{ $m->generic_name }} {{ $m->strength }}</strong> ({{ $m->brand_name }})</label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Lab Tests</label>
                        <div class="border rounded p-2" style="max-height: 110px; overflow-y: auto;">
                            @foreach($labTests as $t)
                                <div class="form-check small">
                                    <input class="form-check-input" type="checkbox" name="lab_test_codes[]" value="{{ $t->code }}" id="flab_{{ $t->id }}">
                                    <label class="form-check-label" for="flab_{{ $t->id }}">{{ $t->code }} — {{ $t->name }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Imaging / ECG / Echo</label>
                        <div class="border rounded p-2" style="max-height: 110px; overflow-y: auto;">
                            @foreach($imagingCatalog as $i)
                                <div class="form-check small">
                                    <input class="form-check-input" type="checkbox" name="imaging_codes[]" value="{{ $i->code }}" id="fimg_{{ $i->id }}">
                                    <label class="form-check-label" for="fimg_{{ $i->id }}">{{ $i->code }} — {{ $i->name }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-success fw-semibold">Save Personal Favorite Bundle</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="hmis-card p-3 mb-4">
            <h2 class="h6 fw-bold border-bottom pb-2 mb-3"><i class="bi bi-bookmark-star text-success me-1"></i>My Personal Favorites ({{ $personalSets->count() }})</h2>
            @forelse($personalSets as $set)
                <div class="border rounded p-2 mb-2 small d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="fw-bold">{{ $set->name }}</div>
                        @if($set->description)<div class="text-muted">{{ $set->description }}</div>@endif
                        <div class="mt-1">
                            <span class="badge bg-light text-dark border">{{ count((array) $set->medications) }} meds</span>
                            <span class="badge bg-light text-dark border">{{ count((array) $set->lab_test_codes) }} labs</span>
                            <span class="badge bg-light text-dark border">{{ count((array) $set->imaging_codes) }} imaging</span>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('emr.favorites.destroy', $set) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">Archive</button>
                    </form>
                </div>
            @empty
                <div class="small text-muted">No personal favorite bundles yet. Create one on the left!</div>
            @endforelse
        </div>

        <div class="hmis-card p-3">
            <h2 class="h6 fw-bold border-bottom pb-2 mb-3"><i class="bi bi-hospital text-primary me-1"></i>Hospital Shared Cardiology Protocols ({{ $hospitalSets->count() }})</h2>
            @foreach($hospitalSets as $set)
                <div class="border rounded p-2 mb-2 small bg-light">
                    <div class="fw-bold">{{ $set->name }}</div>
                    <div class="text-muted">{{ $set->description }}</div>
                    <div class="mt-1">
                        <span class="badge bg-primary">{{ count((array) $set->medications) }} meds</span>
                        <span class="badge bg-secondary">{{ count((array) $set->lab_test_codes) }} labs</span>
                        <span class="badge bg-dark">{{ count((array) $set->imaging_codes) }} imaging</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
