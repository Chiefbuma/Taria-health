<?php
// app/Http/Controllers/DocumentController.php
namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function getApplicationDocuments($applicationId)
    {
        $documents = Document::where('application_id', $applicationId)
            ->with('user')
            ->get();

        return response()->json($documents);
    }

    public function download(Document $document)
    {
        // Check if user has permission to view this document
        if (!Storage::disk('public')->exists($document->file_path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return Storage::disk('public')->download($document->file_path, $document->document_name);
    }

    public function destroy(Document $document)
    {
        $this->authorize('delete', $document);

        // Delete file from storage
        Storage::disk('public')->delete($document->file_path);
        
        $document->delete();

        return response()->json(['message' => 'Document deleted successfully']);
    }
}