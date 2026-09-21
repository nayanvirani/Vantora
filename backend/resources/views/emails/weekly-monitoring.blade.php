<!doctype html>
<html>
<body style="font-family: -apple-system, Helvetica, Arial, sans-serif; color: #111; max-width: 560px; margin: 0 auto; padding: 24px;">
    <h1 style="font-size: 20px;">Your weekly Vantora check-in</h1>

    <p>
        Health Score: <strong>{{ $run->score_before ?? '—' }} → {{ $run->score_after ?? '—' }}</strong>
    </p>

    @php($newIssues = $run->changes['new_issue_codes'] ?? [])
    @if (count($newIssues))
        <p>New issues found this week:</p>
        <ul>
            @foreach ($newIssues as $code)
                <li>{{ $code }}</li>
            @endforeach
        </ul>
    @else
        <p>No new issues found this week — nice work.</p>
    @endif

    <p>
        <a href="{{ config('app.url') }}" style="display: inline-block; background: #111; color: #fff; padding: 10px 18px; border-radius: 6px; text-decoration: none;">
            Open Vantora
        </a>
    </p>
</body>
</html>
