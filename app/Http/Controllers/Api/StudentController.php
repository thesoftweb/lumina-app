<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;

class StudentController extends Controller
{
    /**
     * Retorna lista de alunos com suas turmas
     *
     * GET /api/students
     */
    public function index(): JsonResponse
    {
        try {
            $students = Enrollment::with(['student', 'classroom'])
                ->get()
                ->filter(function ($enrollment) {
                    return $enrollment->student && $enrollment->classroom;
                })
                ->map(function ($enrollment) {
                    return [
                        'id' => (string) $enrollment->student->id,
                        'nome' => $enrollment->student->name,
                        'turma' => $enrollment->classroom->name,
                    ];
                })
                ->unique(fn($item) => $item['id'])
                ->values();

            return response()->json([
                'success' => true,
                'data' => $students,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar alunos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna alunos de uma turma específica
     *
     * GET /api/students/classroom/{classroomId}
     */
    public function byClassroom($classroomId): JsonResponse
    {
        try {
            $students = Enrollment::where('classroom_id', $classroomId)
                ->with(['student', 'classroom'])
                ->get()
                ->filter(function ($enrollment) {
                    return $enrollment->student && $enrollment->classroom;
                })
                ->map(function ($enrollment) {
                    return [
                        'id' => (string) $enrollment->student->id,
                        'nome' => $enrollment->student->name,
                        'turma' => $enrollment->classroom->name,
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $students,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar alunos da turma',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna um aluno específico
     *
     * GET /api/students/{studentId}
     */
    public function show($studentId): JsonResponse
    {
        try {
            $student = Enrollment::whereHas('student', function ($query) use ($studentId) {
                $query->where('id', $studentId);
            }) || !$student->student || !$student->classroom
            ->with(['student', 'classroom'])
            ->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aluno não encontrado',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => (string) $student->student->id,
                    'nome' => $student->student->name,
                    'serie' => $student->classroom->name,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar aluno',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
