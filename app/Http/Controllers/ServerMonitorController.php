<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\MonitoredServerResource;
use App\Models\MonitoredServer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Middleware\PermissionMiddleware;

/**
 * VPSes monitored through the HRIS server agent: listed for anyone who can
 * see Server Storage, managed (add, rename, new token, remove) only with
 * server-storage-manage. The agent itself talks to ServerAgentController.
 */
class ServerMonitorController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['server-storage-menu|server-storage-view']), only: ['index']),
            new Middleware(PermissionMiddleware::using(['server-storage-manage']), only: ['store', 'update', 'regenerateToken', 'destroy']),
        ];
    }

    public function index()
    {
        try {
            $servers = MonitoredServer::orderBy('name')->get();

            return ResponseHelper::jsonResponse(true, 'Servers Retrieved Successfully', MonitoredServerResource::collection($servers), 200);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /** Adds a VPS and returns its install command (with the only copy of its token). */
    public function store(Request $request)
    {
        $data = $this->validated($request);

        try {
            $server = new MonitoredServer([...$data, 'created_by' => Auth::id()]);
            $token = $server->issueToken();
            $server->save();

            return ResponseHelper::jsonResponse(true, 'Server Added Successfully', $this->withInstall($server, $token), 201);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $data = $this->validated($request);

        try {
            $server = MonitoredServer::findOrFail($id);
            $server->update($data);

            return ResponseHelper::jsonResponse(true, 'Server Updated Successfully', new MonitoredServerResource($server), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Server Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Replaces the agent token (e.g. it was exposed, or the install command
     * was lost). The old token stops working immediately, so the agent has
     * to be reinstalled with the new command.
     */
    public function regenerateToken(int $id)
    {
        try {
            $server = MonitoredServer::findOrFail($id);
            $token = $server->issueToken();
            $server->save();

            return ResponseHelper::jsonResponse(true, 'Server Token Regenerated', $this->withInstall($server, $token), 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Server Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            MonitoredServer::findOrFail($id)->delete();

            return ResponseHelper::jsonResponse(true, 'Server Removed Successfully', null, 200);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Server Not Found', null, 404);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: '.$e->getMessage(), null, 500);
        }
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'ip_address' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [], ['name' => 'Nama VPS', 'ip_address' => 'IP Address', 'description' => 'Keterangan']);
    }

    private function withInstall(MonitoredServer $server, string $token): array
    {
        return [
            'server' => new MonitoredServerResource($server),
            'token' => $token,
            'install_command' => sprintf(
                'curl -fsSL "%s" | sudo HRIS_AGENT_TOKEN="%s" bash',
                route('server-agent.install'),
                $token
            ),
            'uninstall_command' => 'sudo /usr/local/bin/hris-server-agent --uninstall',
        ];
    }
}
