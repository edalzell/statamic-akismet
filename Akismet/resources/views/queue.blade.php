@extends('layout')

@section('content')
    <akismet-queue inline-template formset="{{ $formset }}">
        <div class="listing term-listing">
            <div id="publish-controls" class="head sticky">
                <h1 id="publish-title">{{ $title }}</h1>
            </div>

            <div class="card flush">
                <div v-if="loading" class="loading">
                    <span class="icon icon-circular-graph animation-spin"></span> {{ translate('cp.loading') }}
                </div>
                <template v-if="noItems">
                    <div class="no-results">
                        <span class="icon icon-untag"></span>
                        <h2>Congratulations!</h2>
                        <h3>You are spam free!</h3>
                    </div>
                </template>
                <dossier-table v-if="hasItems" :options="tableOptions"></dossier-table>
            </div>
        </div>
    </akismet-queue>

@endsection
