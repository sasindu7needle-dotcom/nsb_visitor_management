@extends('layouts.admin')

@section('title', 'Departments & People')

@section('header')
    <div>
        <span class="tagline no-margin">MASTER CONFIGURATIONS</span>
        <h1>Departments &amp; People<span>.</span></h1>
        <p>Manage the departments visitors can select and the people available to meet.</p>
    </div>
@endsection

@section('content')
    <nav class="configuration-tabs" aria-label="Master configuration sections">
        <a href="{{ route('admin.configurations.event.edit') }}">Event Configurations</a>
        <a href="{{ route('admin.configurations.capacity.edit') }}">Occupancy Limit</a>
        <a href="{{ route('admin.configurations.categories.index') }}">Visitor Categories</a>
        <a class="active" href="{{ route('admin.configurations.departments.index') }}" aria-current="page">Departments &amp; People</a>
        <a href="{{ route('admin.configurations.users.index') }}">Users &amp; Access</a>
    </nav>

    @if(session('status'))
        <div class="admin-page-alert configuration-success" role="status">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"></path></svg>
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="admin-page-alert admin-alert-danger" role="alert" style="margin-bottom:20px;padding:14px 18px;background:#fff1f1;border:1px solid #fecaca;border-radius:10px;color:#991b1b;font-size:12px;font-weight:600;">
            <ul style="margin:0;padding-left:18px;">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="configuration-split-layout">
        <section class="admin-panel configuration-panel">
            <div class="configuration-panel-heading"><div><span>VISIT DIRECTORY</span><h2>Add department or person</h2><p>People are linked to one department and appear only when that department is selected.</p></div></div>

            <form method="POST" action="{{ route('admin.configurations.departments.store') }}" class="configuration-form" style="padding-bottom:20px;border-bottom:1px solid #edf0f2;">
                @csrf
                <label class="configuration-field">
                    <span>Department name <b>*</b></span>
                    <input type="text" name="name" value="{{ old('name') }}" maxlength="150" required placeholder="e.g. Customer Service">
                </label>
                <div class="configuration-actions" style="margin-top:16px;"><p>Add a department first, then assign people to it.</p><button class="btn btn-primary" type="submit">Add Department <span>→</span></button></div>
            </form>

            <form method="POST" action="{{ route('admin.configurations.departments.people.store') }}" class="configuration-form" style="padding-top:20px;">
                @csrf
                <div style="display:grid;gap:16px;">
                    <label class="configuration-field">
                        <span>Department <b>*</b></span>
                        <select name="department_id" required style="width:100%;height:46px;padding:0 14px;border:1px solid #d8e0e7;border-radius:9px;background:#fff;font:500 12px Inter,sans-serif;">
                            <option value="" disabled @selected(!old('department_id'))>Select department</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="configuration-field"><span>Person name <b>*</b></span><input type="text" name="name" value="{{ old('department_id') ? old('name') : '' }}" maxlength="180" required placeholder="e.g. Ms. Jane Perera"></label>
                    <label class="configuration-field"><span>Designation</span><input type="text" name="designation" value="{{ old('designation') }}" maxlength="120" placeholder="e.g. Branch Manager"></label>
                </div>
                <div class="configuration-actions" style="margin-top:20px;"><p>Only active people are displayed on the visitor registration form.</p><button class="btn btn-primary" type="submit">Add Person <span>→</span></button></div>
            </form>
        </section>

        <section class="admin-panel configuration-panel">
            <div class="configuration-panel-heading"><div><span>LIVE DIRECTORY</span><h2>Configured departments</h2><p>{{ $departments->where('is_active', true)->count() }} active departments and {{ $departments->flatMap->people->where('is_active', true)->count() }} available people.</p></div></div>
            <div style="display:grid;gap:12px;">
                @forelse($departments as $department)
                    <article style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 15px;background:#f8fafc;">
                            <div><strong style="font-size:13px;color:#172033;">{{ $department->name }}</strong><small style="display:block;margin-top:2px;color:#64748b;font-size:10px;">{{ $department->people->count() }} person{{ $department->people->count() === 1 ? '' : 's' }}</small></div>
                            <form method="POST" action="{{ route('admin.configurations.departments.toggle', $department) }}">@csrf @method('PATCH')<button type="submit" style="border:0;background:none;padding:0;cursor:pointer;color:{{ $department->is_active ? '#15803d' : '#64748b' }};font-size:10px;font-weight:800;text-transform:uppercase;">{{ $department->is_active ? 'Active' : 'Disabled' }}</button></form>
                        </div>
                        @forelse($department->people as $person)
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:11px 15px;border-top:1px solid #edf0f2;">
                                <div><strong style="font-size:12px;color:#334155;">{{ $person->name }}</strong>@if($person->designation)<small style="margin-left:6px;color:#64748b;font-size:10px;">{{ $person->designation }}</small>@endif</div>
                                <div style="display:flex;gap:8px;align-items:center;">
                                    <form method="POST" action="{{ route('admin.configurations.departments.people.toggle', $person) }}">@csrf @method('PATCH')<button type="submit" style="border:0;background:none;padding:0;cursor:pointer;color:{{ $person->is_active ? '#15803d' : '#64748b' }};font-size:10px;font-weight:800;">{{ $person->is_active ? 'Active' : 'Disabled' }}</button></form>
                                    <form method="POST" action="{{ route('admin.configurations.departments.people.destroy', $person) }}" onsubmit="return confirm('Remove this person from the department?');">@csrf @method('DELETE')<button type="submit" style="border:0;background:none;padding:0;cursor:pointer;color:#dc2626;font-size:10px;font-weight:800;">Remove</button></form>
                                </div>
                            </div>
                        @empty
                            <p style="margin:0;padding:12px 15px;color:#94a3b8;font-size:11px;">No people have been added to this department yet.</p>
                        @endforelse
                    </article>
                @empty
                    <p style="margin:24px 0;text-align:center;color:#94a3b8;font-size:12px;">No departments have been configured yet.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
