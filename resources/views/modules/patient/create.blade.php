@extends('layouts.app')

@section('title', $mode === 'emergency' ? 'Emergency Patient Intake' : 'New Patient Registration')

@section('content')
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">
            @if($mode === 'emergency')
                <span class="text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Fast-Track Emergency Patient Intake</span>
            @else
                <i class="bi bi-person-plus-fill me-2 text-primary"></i>Standard Patient Registration
            @endif
        </h1>
        <small class="text-muted">Generates a permanent, non-recyclable Hospital MRN ({{ config('hmis.facility_code') }}-YYYY-NNNNNN)</small>
    </div>

    <div class="btn-group" role="group" aria-label="Registration Mode">
        <a href="{{ route('patients.create', ['mode' => 'standard']) }}" class="btn {{ $mode === 'standard' ? 'btn-primary' : 'btn-outline-primary' }}">Standard Registration</a>
        <a href="{{ route('patients.create', ['mode' => 'emergency']) }}" class="btn {{ $mode === 'emergency' ? 'btn-danger' : 'btn-outline-danger' }}">Emergency (CNIC Optional)</a>
    </div>
</div>

{{-- Duplicate Warning Panel --}}
@if($duplicateCandidates->isNotEmpty())
    <div class="alert alert-warning border-warning shadow-sm mb-4" role="alert">
        <h2 class="h6 fw-bold mb-2"><i class="bi bi-people-fill me-2"></i>Potential Duplicate Patient(s) Detected</h2>
        <p class="mb-2">{{ $duplicateMessage }}</p>
        <div class="table-responsive bg-white rounded border mb-3">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Match</th>
                        <th>MRN</th>
                        <th>Name &amp; Father/Spouse</th>
                        <th>CNIC</th>
                        <th>Phone</th>
                        <th>Reason</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($duplicateCandidates as $candidate)
                        @php($existing = $candidate['patient'])
                        <tr>
                            <td>
                                <span class="badge {{ $candidate['match_level'] === 'hard' ? 'bg-danger' : 'bg-warning text-dark' }}">
                                    {{ strtoupper($candidate['match_level']) }} ({{ $candidate['score'] }}%)
                                </span>
                            </td>
                            <td class="hmis-mrn-badge">{{ $existing->mrn }}</td>
                            <td>
                                <div class="fw-semibold">{{ $existing->full_name }}</div>
                                <small class="text-muted">{{ $existing->father_or_spouse_name ?? '—' }}</small>
                            </td>
                            <td>{{ $existing->formattedCnic() ?? '—' }}</td>
                            <td>{{ $existing->phone_primary ?? '—' }}</td>
                            <td class="small">{{ implode('; ', $candidate['reasons']) }}</td>
                            <td class="text-end">
                                <a href="{{ route('patients.show', $existing) }}" class="btn btn-sm btn-primary">Use Existing Record</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="small text-dark">
            If you have verified this is a <strong>distinct individual</strong>, check the confirmation box at the bottom of the form and re-submit.
            (Exact CNIC duplicates require <code>patient.register_override_duplicate</code> authorization.)
        </div>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <div class="fw-bold mb-1">Please correct the highlighted fields:</div>
        <ul class="mb-0 small">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('patients.store') }}" class="hmis-card p-3 p-md-4">
    @csrf
    <input type="hidden" name="registration_type" value="{{ old('registration_type', $mode) }}">

    @if($mode === 'emergency')
        <div class="alert alert-danger-subtle border border-danger-subtle d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
            <div>
                <strong>Emergency Protocol:</strong> CNIC, father name, and phone are optional so resuscitation, ECG, lab, and cath lab orders can start immediately.
            </div>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="is_unknown_emergency" name="is_unknown_emergency" value="1" @checked(old('is_unknown_emergency'))>
                <label class="form-check-label fw-semibold" for="is_unknown_emergency">Unidentified / Unconscious Patient</label>
            </div>
        </div>
    @endif

    <h2 class="h6 fw-bold text-primary border-bottom pb-2 mb-3">1. Core Patient Identity</h2>
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <label for="full_name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
            <input type="text" id="full_name" name="full_name" value="{{ old('full_name') }}" class="form-control @error('full_name') is-invalid @enderror" required placeholder="{{ $mode === 'emergency' ? 'Patient name or Unknown Male ER-01' : 'e.g. Muhammad Iqbal' }}">
        </div>
        <div class="col-md-6">
            <label for="father_or_spouse_name" class="form-label fw-semibold">
                Father / Spouse Name @if($mode !== 'emergency')<span class="text-danger">*</span>@endif
            </label>
            <input type="text" id="father_or_spouse_name" name="father_or_spouse_name" value="{{ old('father_or_spouse_name') }}" class="form-control @error('father_or_spouse_name') is-invalid @enderror" @required($mode !== 'emergency')>
        </div>

        <div class="col-md-3">
            <label for="sex" class="form-label fw-semibold">Sex <span class="text-danger">*</span></label>
            <select id="sex" name="sex" class="form-select" required>
                <option value="male" @selected(old('sex') === 'male')>Male</option>
                <option value="female" @selected(old('sex') === 'female')>Female</option>
                <option value="other" @selected(old('sex') === 'other')>Other</option>
                <option value="unknown" @selected(old('sex', $mode === 'emergency' ? 'unknown' : '') === 'unknown')>Unknown</option>
            </select>
        </div>

        <div class="col-md-3">
            <label for="date_of_birth" class="form-label fw-semibold">Date of Birth</label>
            <input type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}" class="form-control @error('date_of_birth') is-invalid @enderror">
        </div>

        <div class="col-md-3">
            <label for="estimated_age_years" class="form-label fw-semibold">Or Estimated Age (Years)</label>
            <input type="number" min="0" max="130" id="estimated_age_years" name="estimated_age_years" value="{{ old('estimated_age_years') }}" class="form-control" placeholder="e.g. 55">
        </div>

        <div class="col-md-3">
            <label for="blood_group" class="form-label fw-semibold">Blood Group</label>
            <select id="blood_group" name="blood_group" class="form-select">
                <option value="">Not Known</option>
                @foreach(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg)
                    <option value="{{ $bg }}" @selected(old('blood_group') === $bg)>{{ $bg }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-4">
            <label for="cnic" class="form-label fw-semibold">CNIC (Optional in Emergencies)</label>
            <input type="text" id="cnic" name="cnic" value="{{ old('cnic') }}" class="form-control @error('cnic') is-invalid @enderror" placeholder="71501-1234567-1">
            <div class="form-text">13 digits; stored normalized, never used as DB primary key.</div>
        </div>

        <div class="col-md-4">
            <label for="sehat_card_number" class="form-label fw-semibold">Sehat Card / Entitlement #</label>
            <input type="text" id="sehat_card_number" name="sehat_card_number" value="{{ old('sehat_card_number') }}" class="form-control" placeholder="Optional">
        </div>

        <div class="col-md-4">
            <label for="marital_status" class="form-label fw-semibold">Marital Status</label>
            <select id="marital_status" name="marital_status" class="form-select">
                <option value="">Select...</option>
                @foreach(['single', 'married', 'widowed', 'divorced', 'unknown'] as $ms)
                    <option value="{{ $ms }}" @selected(old('marital_status') === $ms)>{{ ucfirst($ms) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <h2 class="h6 fw-bold text-primary border-bottom pb-2 mb-3">2. Contact &amp; Address</h2>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <label for="phone_primary" class="form-label fw-semibold">
                Primary Phone @if($mode !== 'emergency')<span class="text-danger">*</span>@endif
            </label>
            <input type="text" id="phone_primary" name="phone_primary" value="{{ old('phone_primary') }}" class="form-control @error('phone_primary') is-invalid @enderror" placeholder="0345-XXXXXXX" @required($mode !== 'emergency')>
        </div>
        <div class="col-md-4">
            <label for="phone_secondary" class="form-label fw-semibold">Secondary Phone</label>
            <input type="text" id="phone_secondary" name="phone_secondary" value="{{ old('phone_secondary') }}" class="form-control">
        </div>
        <div class="col-md-4">
            <label for="district" class="form-label fw-semibold">District</label>
            <input type="text" id="district" name="district" value="{{ old('district', 'Gilgit') }}" class="form-control">
        </div>
        <div class="col-md-6">
            <label for="address_line" class="form-label fw-semibold">Street / Mohallah / Village</label>
            <input type="text" id="address_line" name="address_line" value="{{ old('address_line') }}" class="form-control">
        </div>
        <div class="col-md-3">
            <label for="city" class="form-label fw-semibold">City / Town</label>
            <input type="text" id="city" name="city" value="{{ old('city', 'Gilgit') }}" class="form-control">
        </div>
        <div class="col-md-3">
            <label for="province" class="form-label fw-semibold">Province / Region</label>
            <input type="text" id="province" name="province" value="{{ old('province', 'Gilgit-Baltistan') }}" class="form-control">
        </div>
    </div>

    <h2 class="h6 fw-bold text-primary border-bottom pb-2 mb-3">3. Emergency Contact / Guardian (Required for Minors &lt; 18)</h2>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <label for="emergency_contact_type" class="form-label fw-semibold">Contact Role</label>
            <select id="emergency_contact_type" name="emergency_contact_type" class="form-select">
                <option value="emergency" @selected(old('emergency_contact_type') === 'emergency')>Emergency Contact</option>
                <option value="guardian" @selected(old('emergency_contact_type') === 'guardian')>Legal Guardian (Minor)</option>
                <option value="next_of_kin" @selected(old('emergency_contact_type') === 'next_of_kin')>Next of Kin</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="emergency_contact_name" class="form-label fw-semibold">Contact Full Name</label>
            <input type="text" id="emergency_contact_name" name="emergency_contact_name" value="{{ old('emergency_contact_name') }}" class="form-control @error('emergency_contact_name') is-invalid @enderror">
        </div>
        <div class="col-md-3">
            <label for="emergency_contact_relationship" class="form-label fw-semibold">Relationship</label>
            <input type="text" id="emergency_contact_relationship" name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship') }}" class="form-control" placeholder="Father, Son, Spouse...">
        </div>
        <div class="col-md-3">
            <label for="emergency_contact_phone" class="form-label fw-semibold">Contact Phone</label>
            <input type="text" id="emergency_contact_phone" name="emergency_contact_phone" value="{{ old('emergency_contact_phone') }}" class="form-control">
        </div>
        <div class="col-md-4">
            <label for="emergency_contact_cnic" class="form-label fw-semibold">Guardian CNIC (Optional)</label>
            <input type="text" id="emergency_contact_cnic" name="emergency_contact_cnic" value="{{ old('emergency_contact_cnic') }}" class="form-control">
        </div>
        <div class="col-md-8">
            <label for="registration_notes" class="form-label fw-semibold">Registration / Triage Notes</label>
            <input type="text" id="registration_notes" name="registration_notes" value="{{ old('registration_notes') }}" class="form-control" placeholder="e.g., Brought by Rescue 1122, referral details...">
        </div>
    </div>

    @if($duplicateCandidates->isNotEmpty())
        <div class="form-check mb-4 p-3 rounded bg-warning-subtle border border-warning">
            <input class="form-check-input ms-0 me-2" type="checkbox" id="confirm_distinct" name="confirm_distinct" value="1">
            <label class="form-check-label fw-semibold" for="confirm_distinct">
                I have reviewed the candidate records above and confirm this patient is a distinct individual.
            </label>
        </div>
    @endif

    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('patients.index') }}" class="btn btn-outline-secondary btn-touch">Cancel</a>
        <button type="submit" class="btn {{ $mode === 'emergency' ? 'btn-danger' : 'btn-primary' }} btn-touch px-4 fw-semibold">
            <i class="bi bi-check2-circle me-1"></i>
            {{ $mode === 'emergency' ? 'Issue Emergency MRN Now' : 'Register & Generate Permanent MRN' }}
        </button>
    </div>
</form>
@endsection
