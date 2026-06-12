<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Session\StoreSessionRequest;
use App\Http\Requests\Session\UpdateSessionRequest;
use App\Http\Resources\SessionResource;
use App\Models\AcademicSession;
use App\Services\SessionService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class SessionController extends ApiController
{
    public function __construct(private readonly SessionService $sessions) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $sessions = AcademicSession::query()->orderByDesc('id')->get();

        return $this->success(SessionResource::collection($sessions));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSessionRequest $request): JsonResponse
    {
        $session = $this->sessions->create($request->validated());

        return $this->success(SessionResource::make($session), 'Session created', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(AcademicSession $session): JsonResponse
    {
        return $this->success(SessionResource::make($session));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSessionRequest $request, AcademicSession $session): JsonResponse
    {
        $session = $this->sessions->update($session, $request->validated());

        return $this->success(SessionResource::make($session), 'Session updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AcademicSession $session): JsonResponse
    {
        if ($session->is_current) {
            return $this->error('One session must be current', 422);
        }

        try {
            $session->delete();
        } catch (QueryException) {
            return $this->error('Session is in use and cannot be deleted', 409);
        }

        return $this->success(null, 'Session deleted');
    }
}
