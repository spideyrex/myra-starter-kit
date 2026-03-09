<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ImportController extends Controller
{
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
            'resource' => 'required|in:users',
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        $headers = fgetcsv($handle);

        if (!$headers) {
            fclose($handle);
            return response()->json(['error' => 'Unable to read CSV headers.'], 422);
        }

        // Clean BOM from first header
        $headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]);
        $headers = array_map('trim', $headers);

        $preview = [];
        $rowCount = 0;
        while (($row = fgetcsv($handle)) !== false && $rowCount < 5) {
            $preview[] = array_combine($headers, array_pad($row, count($headers), ''));
            $rowCount++;
        }

        // Count remaining rows
        while (fgetcsv($handle) !== false) {
            $rowCount++;
        }

        fclose($handle);

        return response()->json([
            'headers' => $headers,
            'preview' => $preview,
            'total_rows' => $rowCount,
        ]);
    }

    public function execute(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
            'resource' => 'required|in:users',
            'mapping' => 'required|array',
        ]);

        $file = $request->file('file');
        $mapping = $request->mapping;

        $handle = fopen($file->getRealPath(), 'r');
        $headers = fgetcsv($handle);
        $headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]);
        $headers = array_map('trim', $headers);

        $imported = 0;
        $errors = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            $data = array_combine($headers, array_pad($row, count($headers), ''));

            // Map CSV columns to model fields
            $mapped = [];
            foreach ($mapping as $modelField => $csvColumn) {
                if ($csvColumn && isset($data[$csvColumn])) {
                    $mapped[$modelField] = $data[$csvColumn];
                }
            }

            if ($request->resource === 'users') {
                $result = $this->importUserRow($mapped, $rowNum);
                if ($result === true) {
                    $imported++;
                } else {
                    $errors[] = $result;
                }
            }

            if (count($errors) >= 10) {
                break; // Stop after 10 errors
            }
        }

        fclose($handle);

        return response()->json([
            'imported' => $imported,
            'errors' => $errors,
        ]);
    }

    private function importUserRow(array $data, int $rowNum): true|string
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'nullable|string|min:8',
        ]);

        if ($validator->fails()) {
            $errs = implode(', ', $validator->errors()->all());
            return "Row {$rowNum}: {$errs}";
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password'] ?? 'password'),
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        if (!empty($data['role'])) {
            $user->assignRole($data['role']);
        }

        return true;
    }
}
