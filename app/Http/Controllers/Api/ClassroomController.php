<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\JsonResponse;

class ClassroomController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $classrooms = Classroom::get(['id', 'name'])
                ->map(function ($classroom) {
                    return [
                        'id' => (string) $classroom->id,
                        'nome' => $classroom->name,
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $classrooms,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar turmas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($classroomId): JsonResponse
    {
        try {
            $classroom = Classroom::find($classroomId, ['id', 'name']);

            if (!$classroom) {
                return response()->json([
                    'success' => false,
                    'message' => 'Turma não encontrada',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => (string) $classroom->id,
                    'name' => $classroom->name,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar turma',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
