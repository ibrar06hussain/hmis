@extends('layouts.app')

@section('title', 'Edit Demographics — '.$patient->mrn)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 fw-bold mb-0">Edit Patient Demographics</h1>
        <small class="text-muted">Permanent MRN <span class="hmis-mrn-badge text-primary">{{ $patient->mrn }}</span> is immutable.</small>
    </div>
    <a href="{{ route('patients.show', $patient) }}" class="btn btn-outline-secondary">Back to Profile</a>
</div>

@if($errors->any())
    <div class="alert alert-danger small">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('patients.update', $patient) }}" class="hmis-card p-3 p-md-4">
    @csrf
    @method('PUT')

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <label for="full_name" class="form-label fw-semibold">Full Name</label>
            <input type="text" id="full_name" name="full_name" value="{{ old('full_name', $patient->full_name) }}" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label for="father_or_spouse_name" class="form-label fw-semibold">Father / Spouse Name</label>
            <input type="text" id="father_or_spouse_name" name="father_or_spouse_name" value="{{ old('father_or_spouse_name', $patient->father_or_spouse_name) }}" class="form-control">
        </div>
        <div class="col-md-3">
            <label for="sex" class="form-label fw-semibold">Sex</label>
            <select id="sex" name="sex" class="form-select" required>
                @foreach(['male', 'female', 'other', 'unknown'] as $sex)
                    <option value="{{ $sex }}" @selected(old('sex', $patient->sex) === $sex)>{{ ucfirst($sex) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label for="date_of_birth" class="form-label fw-semibold">Date of Birth</label>
            <input type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $patient->date_of_birth?->format('Y-m-d')) }}" class="form-control">
        </div>
        <div class="col-md-3">
            <label for="blood_group" class="form-label fw-semibold">Blood Group</label>
            <select id="blood_group" name="blood_group" class="form-select">
                <option value="">Not Known</option>
                @foreach(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg)
                    <option value="{{ $bg }}" @selected(old('blood_group', $patient->blood_group) === $bg)>{{ $bg }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label for="cnic" class="form-label fw-semibold">CNIC (13 digits)</label>
            <input type="text" id="cnic" name="cnic" value="{{ old('cnic', $patient->formattedCnic()) }}" class="form-control" placeholder="71501-1234567-1">
        </div>
        <div class="col-md-4">
            <label for="phone_primary" class="form-label fw-semibold">Primary Phone</label>
            <input type="text" id="phone_primary" name="phone_primary" value="{{ old('phone_primary', $patient->phone_primary) }}" class="form-control">
        </div>
        <div class="col-md-4">
            <label for="district" class="form-label fw-semibold">District</label>
            <input type="text" id="district" name="district" value="{{ old('district', $patient->district) }}" class="form-control">
        </div>
        <div class="col-md-4">
            <label for="province" class="form-label fw-semibold">Province / Region</label>
            <input type="text" id="province" name="province" value="{{ old('province', $patient->province) }}" class="form-control">
        </div>
        <div class="col-12">
            <label for="address_line" class="form-label fw-semibold">Address</label>
            <input type="text" id="address_line" name="address_line" value="{{ old('address_line', $patient->address_line) }}" class="form-control">
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('patients.show', $patient) }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary fw-semibold">Save Demographics</button>
    </div>
</form>
@endsection
