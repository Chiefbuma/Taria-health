<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use Illuminate\Http\Request;


class ClinicController extends Controller
{
    public function index()
    {
        try {
            $clinics = Clinic::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']);

            return response()->json([
                'success' => true,
                'clinics' => $clinics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch clinics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}