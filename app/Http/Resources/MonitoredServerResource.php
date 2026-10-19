<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonitoredServerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $report = $this->last_report;
        $pct = fn ($used, $total) => $total > 0 ? round($used / $total * 100, 1) : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'ip_address' => $this->ip_address,
            'description' => $this->description,
            'token_prefix' => $this->token_prefix,
            'status' => $this->status(),
            'last_report_at' => $this->last_report_at,
            'last_report_ip' => $this->last_report_ip,
            'report' => $report ? [
                'hostname' => $report['hostname'] ?? null,
                'os' => $report['os'] ?? null,
                'agent_version' => $report['agent_version'] ?? null,
                'uptime_seconds' => $report['uptime_seconds'] ?? null,
                'disk' => [
                    ...$report['disk'],
                    'used_percent' => $pct($report['disk']['used'] ?? 0, $report['disk']['total'] ?? 0),
                ],
                'memory' => [
                    ...$report['memory'],
                    'used_percent' => $pct($report['memory']['used'] ?? 0, $report['memory']['total'] ?? 0),
                ],
                'cpu' => $report['cpu'],
                'breakdown' => $report['breakdown'] ?? [],
                'breakdown_at' => isset($report['breakdown_at']) ? date(DATE_ATOM, (int) $report['breakdown_at']) : null,
            ] : null,
            'created_at' => $this->created_at,
        ];
    }
}
