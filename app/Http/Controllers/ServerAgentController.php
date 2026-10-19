<?php

namespace App\Http\Controllers;

use App\Models\MonitoredServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Public endpoints used by the HRIS server agent on each VPS (no login --
 * the per-server token is the only credential for reporting):
 *  - install.sh / agent.py: the installer and the agent itself
 *  - report: the agent's periodic disk / memory / CPU report
 */
class ServerAgentController extends Controller
{
    public function installScript()
    {
        $script = str_replace(
            ['__REPORT_URL__', '__AGENT_URL__'],
            [route('server-agent.report'), route('server-agent.script')],
            file_get_contents(resource_path('server-agent/install.sh'))
        );

        return response($script, 200, ['Content-Type' => 'text/x-shellscript; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    public function agentScript()
    {
        return response(file_get_contents(resource_path('server-agent/agent.py')), 200, [
            'Content-Type' => 'text/x-python; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function report(Request $request)
    {
        // Sent in its own header rather than Authorization, so it never
        // goes through Sanctum's user-token lookup.
        $server = MonitoredServer::findByToken($request->header('X-Agent-Token'));
        if (! $server) {
            return response()->json(['success' => false, 'message' => 'Invalid agent token.', 'data' => null], 401);
        }

        $int = ['required', 'integer', 'min:0'];
        $validator = Validator::make($request->all(), [
            'agent_version' => ['nullable', 'string', 'max:20'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'os' => ['nullable', 'string', 'max:255'],
            'uptime_seconds' => ['nullable', 'integer', 'min:0'],
            'disk.mount' => ['nullable', 'string', 'max:255'],
            'disk.total' => $int,
            'disk.used' => $int,
            'disk.free' => $int,
            'memory.total' => $int,
            'memory.used' => $int,
            'memory.available' => $int,
            'memory.swap_total' => ['nullable', 'integer', 'min:0'],
            'memory.swap_used' => ['nullable', 'integer', 'min:0'],
            'cpu.cores' => ['required', 'integer', 'min:1', 'max:4096'],
            'cpu.usage_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'cpu.load1' => ['required', 'numeric', 'min:0'],
            'cpu.load5' => ['required', 'numeric', 'min:0'],
            'cpu.load15' => ['required', 'numeric', 'min:0'],
            'breakdown' => ['nullable', 'array', 'max:30'],
            'breakdown.*.path' => ['required', 'string', 'max:255'],
            'breakdown.*.bytes' => $int,
            'breakdown_at' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Invalid report.', 'data' => $validator->errors()], 422);
        }

        // Only the validated fields are stored -- nothing else the request
        // might carry.
        $server->forceFill([
            'last_report' => $validator->validated(),
            'last_report_at' => now(),
            'last_report_ip' => $request->ip(),
        ])->saveQuietly();

        return response()->json(['success' => true, 'message' => 'Report received.', 'data' => null], 200);
    }
}
