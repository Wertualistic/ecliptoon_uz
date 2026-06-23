<?php

namespace App\Http\Controllers;

use App\Models\Sponsor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SponsorController extends Controller
{
    /**
     * Helper to verify if user has a specific permission.
     */
    private function checkPermission(Request $request, string $permission)
    {
        $user = $request->user();
        if (!$user) {
            abort(403, 'Ushbu amalni bajarish uchun sizda huquq yo\'q.');
        }
        if ($user->role === 'admin') {
            return;
        }
        $hasPermission = \App\Models\RolePermission::where('role', $user->role)
            ->where('permission', $permission)
            ->exists();
        if (!$hasPermission) {
            abort(403, 'Ushbu amalni bajarish uchun sizda huquq yo\'q.');
        }
    }

    /**
     * Public list of active sponsors.
     */
    public function publicIndex()
    {
        $sponsors = Sponsor::where('is_active', true)->orderBy('created_at', 'desc')->get()->map(function ($sp) {
            return [
                'id' => $sp->id,
                'name' => $sp->name,
                'logo_url' => asset('storage/' . $sp->logo_path),
                'link_url' => $sp->link_url,
            ];
        });

        return response()->json($sponsors);
    }

    /**
     * Admin: List all sponsors.
     */
    public function index(Request $request)
    {
        $this->checkPermission($request, 'sponsors');
        $sponsors = Sponsor::orderBy('created_at', 'desc')->get()->map(function ($sp) {
            return [
                'id' => $sp->id,
                'name' => $sp->name,
                'logo_url' => asset('storage/' . $sp->logo_path),
                'link_url' => $sp->link_url,
                'is_active' => $sp->is_active,
                'created_at' => $sp->created_at,
            ];
        });

        return response()->json($sponsors);
    }

    /**
     * Admin: Store a new sponsor.
     */
    public function store(Request $request)
    {
        $this->checkPermission($request, 'sponsors');
        $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'required|image|max:2048', // Max 2MB logo
            'link_url' => 'nullable|string|url|max:255',
        ]);

        $logoPath = $request->file('logo')->store('sponsors', 'public');

        $sponsor = Sponsor::create([
            'name' => $request->name,
            'logo_path' => $logoPath,
            'link_url' => $request->link_url,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Hamkor muvaffaqiyatli qo\'shildi.', // "Partner successfully added."
            'sponsor' => $sponsor,
        ], 201);
    }

    /**
     * Admin: Delete a sponsor.
     */
    public function destroy($id, Request $request)
    {
        $this->checkPermission($request, 'sponsors');
        $sponsor = Sponsor::findOrFail($id);

        if ($sponsor->logo_path) {
            Storage::disk('public')->delete($sponsor->logo_path);
        }

        $sponsor->delete();

        return response()->json([
            'message' => 'Hamkor muvaffaqiyatli o\'chirildi.' // "Partner successfully deleted."
        ]);
    }
}
