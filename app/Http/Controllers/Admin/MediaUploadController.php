<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MediaStorageService;
use Illuminate\Http\Request;

class MediaUploadController extends Controller
{
    public function upload(Request $request, MediaStorageService $storage)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpg,jpeg,png,webp,gif|max:5120',
            'folder' => 'nullable|in:banners,blog,guides,media',
            'prefix' => 'nullable|string|max:40',
        ], [
            'image.required' => 'Vui lòng chọn tệp ảnh cần tải lên.',
            'image.mimes' => 'Chỉ hỗ trợ ảnh JPG, PNG, WebP hoặc GIF (chặn SVG/HTML để đảm bảo an toàn).',
            'image.max' => 'Dung lượng ảnh tối đa 5MB.',
        ]);

        try {
            $result = $storage->uploadImage(
                $request->file('image'),
                (string) $request->input('folder', 'media'),
                $request->input('prefix')
            );

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
