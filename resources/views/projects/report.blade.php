<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Report – {{ $data['project']['name'] }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 13px; color: #1e293b; background: #fff; }
        .page { padding: 48px 60px; max-width: 900px; margin: 0 auto; }

        /* Cover */
        .cover { min-height: 260px; border-bottom: 3px solid #3B82F6; padding-bottom: 40px; margin-bottom: 40px; }
        .cover-logo { width: 48px; height: 48px; background: #3B82F6; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 32px; }
        .cover-logo-text { color: #fff; font-size: 22px; font-weight: 700; }
        .cover-title { font-size: 28px; font-weight: 700; color: #0f172a; letter-spacing: -0.02em; margin-bottom: 8px; }
        .cover-subtitle { font-size: 15px; color: #64748b; margin-bottom: 24px; }
        .cover-meta { display: flex; gap: 40px; }
        .cover-meta-item label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; display: block; margin-bottom: 4px; }
        .cover-meta-item span { font-size: 13px; font-weight: 600; color: #334155; }

        /* Sections */
        .section { margin-bottom: 36px; }
        .section-title { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #3B82F6; font-weight: 700; margin-bottom: 16px; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0; }

        /* Summary cards */
        .cards { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
        .card { flex: 1; min-width: 130px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; }
        .card-label { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 6px; }
        .card-value { font-size: 22px; font-weight: 700; color: #0f172a; }
        .card-sub { font-size: 11px; color: #64748b; margin-top: 2px; }

        /* Progress bar */
        .progress-wrap { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
        .progress-bar { flex: 1; height: 10px; background: #e2e8f0; border-radius: 99px; overflow: hidden; }
        .progress-fill { height: 100%; background: #3B82F6; border-radius: 99px; }
        .progress-label { font-size: 13px; font-weight: 600; color: #334155; min-width: 44px; text-align: right; }

        /* Tables */
        table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        th { text-align: left; font-size: 11px; letter-spacing: 0.06em; text-transform: uppercase; color: #94a3b8; font-weight: 600; padding: 8px 12px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #334155; vertical-align: middle; }
        tr:last-child td { border-bottom: 0; }
        tr:hover td { background: #f8fafc; }

        /* Badges */
        .badge { display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-blue { background: #dbeafe; color: #1d4ed8; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .badge-slate { background: #f1f5f9; color: #475569; }
        .badge-red { background: #fee2e2; color: #991b1b; }

        .text-right { text-align: right; }
        .text-muted { color: #94a3b8; }
        .font-bold { font-weight: 700; }

        /* Footer */
        .footer { margin-top: 48px; padding-top: 16px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; font-size: 11px; color: #94a3b8; }

        @media print {
            .page { padding: 24px; }
            .cover { page-break-after: always; }
        }
    </style>
</head>
<body>
<div class="page">

    <!-- Cover Page -->
    <div class="cover">
        <div class="cover-logo">
            <span class="cover-logo-text">W</span>
        </div>
        <div class="cover-title">{{ $data['project']['name'] }}</div>
        <div class="cover-subtitle">Project Status Report</div>
        <div class="cover-meta">
            <div class="cover-meta-item">
                <label>Project Code</label>
                <span>{{ $data['project']['code'] ?? '—' }}</span>
            </div>
            <div class="cover-meta-item">
                <label>Owner</label>
                <span>{{ $data['project']['owner'] ?? '—' }}</span>
            </div>
            <div class="cover-meta-item">
                <label>Start Date</label>
                <span>{{ $data['project']['start_date'] ?? '—' }}</span>
            </div>
            <div class="cover-meta-item">
                <label>End Date</label>
                <span>{{ $data['project']['end_date'] ?? '—' }}</span>
            </div>
            <div class="cover-meta-item">
                <label>Generated</label>
                <span>{{ now()->format('Y-m-d') }}</span>
            </div>
        </div>
    </div>

    <!-- Executive Summary -->
    <div class="section">
        <div class="section-title">Executive Summary</div>
        <div class="cards">
            <div class="card">
                <div class="card-label">Progress</div>
                <div class="card-value">{{ $data['progress'] }}%</div>
                <div class="progress-wrap" style="margin-top:8px">
                    <div class="progress-bar"><div class="progress-fill" style="width:{{ $data['progress'] }}%"></div></div>
                </div>
            </div>
            <div class="card">
                <div class="card-label">Total Tasks</div>
                <div class="card-value">{{ $data['task_summary']['total'] }}</div>
                <div class="card-sub">{{ $data['task_summary']['done'] ?? 0 }} completed</div>
            </div>
            <div class="card">
                <div class="card-label">Total Hours</div>
                <div class="card-value">{{ number_format($data['total_hours'], 1) }}h</div>
            </div>
            @if($data['project']['budget'])
            <div class="card">
                <div class="card-label">Budget</div>
                <div class="card-value">{{ number_format((float)$data['project']['budget'], 0) }}</div>
                <div class="card-sub">{{ $data['project']['currency'] }} · {{ number_format($data['budget']['spent'] ?? 0, 0) }} spent</div>
            </div>
            @endif
        </div>
    </div>

    <!-- Task Breakdown -->
    <div class="section">
        <div class="section-title">Task Breakdown by Status</div>
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th class="text-right">Count</th>
                    <th class="text-right">Percentage</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $statusLabels = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'review' => 'In Review', 'done' => 'Done', 'cancelled' => 'Cancelled'];
                    $statusBadges = ['todo' => 'badge-slate', 'in_progress' => 'badge-blue', 'review' => 'badge-amber', 'done' => 'badge-green', 'cancelled' => 'badge-red'];
                    $total = $data['task_summary']['total'] ?: 1;
                @endphp
                @foreach($data['task_summary']['by_status'] as $status => $count)
                <tr>
                    <td><span class="badge {{ $statusBadges[$status] ?? 'badge-slate' }}">{{ $statusLabels[$status] ?? $status }}</span></td>
                    <td class="text-right font-bold">{{ $count }}</td>
                    <td class="text-right text-muted">{{ number_format($count / $total * 100, 1) }}%</td>
                </tr>
                @endforeach
                @if(empty($data['task_summary']['by_status']))
                <tr><td colspan="3" class="text-muted" style="text-align:center;padding:24px">No tasks yet.</td></tr>
                @endif
            </tbody>
        </table>
    </div>

    <!-- Team Members & Hours -->
    @if(!empty($data['team_hours']))
    <div class="section">
        <div class="section-title">Team Hours</div>
        <table>
            <thead>
                <tr>
                    <th>Member</th>
                    <th class="text-right">Total Hours</th>
                    <th class="text-right">Billable Hours</th>
                    @if($data['project']['is_billable'])
                    <th class="text-right">Rate</th>
                    <th class="text-right">Amount</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($data['team_hours'] as $member)
                <tr>
                    <td class="font-bold">{{ $member['name'] }}</td>
                    <td class="text-right">{{ $member['total_hours'] }}h</td>
                    <td class="text-right">{{ $member['billable_hours'] }}h</td>
                    @if($data['project']['is_billable'])
                    <td class="text-right text-muted">{{ $member['hourly_rate'] ? number_format($member['hourly_rate'], 2) : '—' }}</td>
                    <td class="text-right font-bold">{{ $member['billable_amount'] ? number_format($member['billable_amount'], 2) : '—' }}</td>
                    @endif
                </tr>
                @endforeach
                <tr style="background:#f8fafc">
                    <td class="font-bold">Total</td>
                    <td class="text-right font-bold">{{ $data['total_hours'] }}h</td>
                    <td class="text-right font-bold">{{ collect($data['team_hours'])->sum('billable_hours') }}h</td>
                    @if($data['project']['is_billable'])
                    <td></td>
                    <td class="text-right font-bold">{{ number_format(collect($data['team_hours'])->sum('billable_amount'), 2) }}</td>
                    @endif
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    <!-- Milestones -->
    @if(!empty($data['milestones']))
    <div class="section">
        <div class="section-title">Milestones</div>
        <table>
            <thead>
                <tr>
                    <th>Milestone</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['milestones'] as $milestone)
                <tr>
                    <td class="font-bold">{{ $milestone['name'] }}</td>
                    <td class="text-muted">{{ $milestone['due_date'] ?? '—' }}</td>
                    <td>
                        @if($milestone['reached'])
                            <span class="badge badge-green">Reached</span>
                        @else
                            <span class="badge badge-slate">Pending</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Budget -->
    @if($data['project']['budget'])
    <div class="section">
        <div class="section-title">Budget</div>
        <div class="cards">
            <div class="card">
                <div class="card-label">Estimated Budget</div>
                <div class="card-value">{{ number_format((float)$data['project']['budget'], 0) }}</div>
                <div class="card-sub">{{ $data['project']['currency'] }}</div>
            </div>
            <div class="card">
                <div class="card-label">Spent</div>
                <div class="card-value">{{ number_format($data['budget']['spent'] ?? 0, 0) }}</div>
                <div class="card-sub">{{ $data['project']['currency'] }}</div>
            </div>
            <div class="card">
                <div class="card-label">Remaining</div>
                <div class="card-value">{{ number_format($data['budget']['remaining'] ?? 0, 0) }}</div>
                <div class="card-sub">{{ $data['project']['currency'] }}</div>
            </div>
        </div>
        @php
            $pct = $data['project']['budget'] > 0
                ? min(100, round(($data['budget']['spent'] / $data['project']['budget']) * 100, 1))
                : 0;
        @endphp
        <div class="progress-wrap">
            <div class="progress-bar"><div class="progress-fill" style="width:{{ $pct }}%;background:{{ $pct > 90 ? '#ef4444' : '#3B82F6' }}"></div></div>
            <div class="progress-label">{{ $pct }}%</div>
        </div>
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <span>WideHalo ERP — Confidential</span>
        <span>Generated {{ now()->format('Y-m-d H:i') }}</span>
    </div>

</div>
</body>
</html>
