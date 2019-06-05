<div id="akistmet" class="card flush">
    @foreach ($spam as $form)
    <h1>{{ $form['title'] }}</h1>
    @if ($form['count'] > 0)
    <p>You have {{ $form['count'] }} new items in your <a href="{{ $form['route'] }}">queue</a>.</p>
    @else
    <p>You have nothing in your queue.</p>
    @endif

    @endforeach
</div>