<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeBase;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KnowledgeBaseController extends Controller
{
    public function index()
    {
        $knowledges = KnowledgeBase::latest()->paginate(20)->through(fn (KnowledgeBase $k) => [
            'id' => $k->id,
            'title' => $k->title,
            'content' => $k->content,
            'is_active' => (bool) $k->is_active,
            'updated_at' => $k->updated_at?->format('d/m/Y H:i'),
        ]);

        return Inertia::render('Admin/KnowledgeBases/Index', [
            'knowledges' => $knowledges,
        ]);
    }

    public function create()
    {
        return redirect()->route('admin.knowledge-bases.index');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_active' => 'required|boolean',
        ]);

        KnowledgeBase::create($validated);

        return back()->with('success', 'Đã thêm tài liệu tri thức mới cho AI Chatbot.');
    }

    public function edit(KnowledgeBase $knowledgeBase)
    {
        return redirect()->route('admin.knowledge-bases.index');
    }

    public function update(Request $request, KnowledgeBase $knowledgeBase)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_active' => 'required|boolean',
        ]);

        $knowledgeBase->update($validated);

        return back()->with('success', 'Đã cập nhật tài liệu tri thức.');
    }

    public function destroy(KnowledgeBase $knowledgeBase)
    {
        $kbId = $knowledgeBase->id;
        $knowledgeBase->delete();

        if (preg_match('#/admin/knowledge-bases/' . $kbId . '(?:[/?#]|$)#', (string) url()->previous())) {
            return redirect()->route('admin.knowledge-bases.index')->with('success', 'Đã xóa tài liệu tri thức.');
        }

        return back()->with('success', 'Đã xóa tài liệu tri thức.');
    }
}
