<?php

declare(strict_types=1);

namespace Aftandilmmd\WorkflowAutomation\Http\Controllers;

use Aftandilmmd\WorkflowAutomation\Credentials\CredentialTypeRegistry;
use Aftandilmmd\WorkflowAutomation\Http\Requests\StoreCredentialRequest;
use Aftandilmmd\WorkflowAutomation\Http\Requests\UpdateCredentialRequest;
use Aftandilmmd\WorkflowAutomation\Http\Resources\CredentialResource;
use Aftandilmmd\WorkflowAutomation\Models\WorkflowCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

final class CredentialController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CredentialResource::collection(
            WorkflowCredential::latest()->get()
        );
    }

    public function store(StoreCredentialRequest $request): CredentialResource
    {
        $credential = WorkflowCredential::create($request->validated());

        return new CredentialResource($credential);
    }

    public function show(WorkflowCredential $credential): CredentialResource
    {
        return new CredentialResource($credential);
    }

    public function update(UpdateCredentialRequest $request, WorkflowCredential $credential): CredentialResource
    {
        $credential->update($request->validated());

        return new CredentialResource($credential);
    }

    public function destroy(WorkflowCredential $credential): JsonResponse
    {
        $credential->delete();

        return response()->json(['message' => 'Credential deleted.']);
    }

    public function types(CredentialTypeRegistry $registry): JsonResponse
    {
        return response()->json(['data' => $registry->all()]);
    }
}
