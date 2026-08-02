<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DepartmentPerson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDepartmentDirectoryController extends Controller
{
    public function index(): View
    {
        $departments = Department::with(['people' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        return view('admin.configurations.departments', compact('departments'));
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:departments,name'],
        ]);

        Department::create(['name' => trim($validated['name'])]);

        return redirect()->route('admin.configurations.departments.index')
            ->with('status', 'Department added successfully.');
    }

    public function storePerson(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:180'],
            'designation' => ['nullable', 'string', 'max:120'],
        ]);

        $alreadyExists = DepartmentPerson::where('department_id', $validated['department_id'])
            ->where('name', trim($validated['name']))
            ->exists();

        if ($alreadyExists) {
            return back()->withInput()->withErrors(['name' => 'This person is already listed in the selected department.']);
        }

        DepartmentPerson::create([
            ...$validated,
            'name' => trim($validated['name']),
            'designation' => filled($validated['designation'] ?? null) ? trim($validated['designation']) : null,
        ]);

        return redirect()->route('admin.configurations.departments.index')
            ->with('status', 'Person added to the department successfully.');
    }

    public function toggleDepartment(Department $department): RedirectResponse
    {
        $department->update(['is_active' => ! $department->is_active]);

        return redirect()->route('admin.configurations.departments.index')
            ->with('status', 'Department availability updated.');
    }

    public function togglePerson(DepartmentPerson $person): RedirectResponse
    {
        $person->update(['is_active' => ! $person->is_active]);

        return redirect()->route('admin.configurations.departments.index')
            ->with('status', 'Person availability updated.');
    }

    public function destroyPerson(DepartmentPerson $person): RedirectResponse
    {
        $person->delete();

        return redirect()->route('admin.configurations.departments.index')
            ->with('status', 'Person removed from the department.');
    }
}
