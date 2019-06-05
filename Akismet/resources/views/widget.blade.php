<div class="card flush">
    <div class="head">
        <h1><a href="/cp/addons/akismet/queue">Spam Queue</a></h1>
    </div>
    <div class="card-body pad-16">
        @foreach ($spam as $form)
        <h2 {{ $form === reset($spam) ? 'class=mt-0' : '' }}>{{ $form['title'] }}</h2>
        <p>
            @if ($form['count'] > 0)
            You have {{ $form['count'] }} new items in your&nbsp;<a href="{{ $form['route'] }}">queue</a>.
            @else
            You have nothing in your&nbsp;queue.
            @endif
        </p>
        @endforeach
    </div>
</div>