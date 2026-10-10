<div id="cd-lookup">
    <form id="cd-lookup-form">
        <div class="cdl-field">
            <label for="cd-lookup-address">Find Your Representative</label>
            <input
                type="text"
                id="cd-lookup-address"
                name="address"
                placeholder="123 Main St, City, State ZIP"
                required
            >
        </div>
        <button type="submit"><span class="cdl-btn-text">Search</span></button>
    </form>

    <div id="cd-lookup-results" hidden></div>
</div>

<script>
(function () {
    // Scoped to this shortcode instance's own container, since WordPress allows
    // [cd_lookup] to appear more than once on a page — document.getElementById()
    // would always bind to the first instance's elements otherwise.
    const container = document.currentScript.previousElementSibling;
    const endpoint = <?php echo wp_json_encode( rest_url( 'cd-lookup/v1/representatives' ) ); ?>;
    const nonce    = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
    const civicdogUrl = <?php echo wp_json_encode( cd_lookup_civicdog_app_url() ); ?>;
    // Admin-curated topics (Settings > CD Lookup). `label` and `group` are
    // pre-escaped for innerHTML; the raw `topic` only ever goes through
    // encodeURIComponent.
    const voteTopics  = <?php echo wp_json_encode( array_map( fn ( $entry ) => [ 'label' => cd_lookup_esc( $entry['topic'] ), 'topic' => $entry['topic'], 'group' => $entry['group'] === null ? null : cd_lookup_esc( $entry['group'] ) ], cd_lookup_vote_topics() ) ); ?>;

    container.querySelector('#cd-lookup-form').addEventListener('submit', async function (e) {
        e.preventDefault();

        const address = container.querySelector('#cd-lookup-address').value.trim();
        const results = container.querySelector('#cd-lookup-results');

        results.innerHTML = 'Loading&hellip;';
        results.hidden = false;

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce,
                },
                body: JSON.stringify({ address }),
            });

            if (!response.ok) {
                throw new Error(await errorMessageFromResponse(response));
            }

            const data = await response.json();
            results.innerHTML = renderResults(data);
        } catch (err) {
            results.innerHTML = '<p>Error: ' + err.message + '</p>';
        }
    });

    // Point a card's votes link at the chosen topic on CivicDog (which
    // runs the topic search itself from ?topic=). Delegated, since the cards
    // are re-rendered on every lookup.
    container.querySelector('#cd-lookup-results').addEventListener('change', function (e) {
        const select = e.target.closest('.cdl-topic');
        if (!select) return;
        const link  = select.closest('.cdl-votes').querySelector('.cdl-votes-link');
        const topic = voteTopics[select.value];
        if (topic) {
            link.href = `${civicdogUrl}/member/${select.dataset.bioguide}?topic=${encodeURIComponent(topic.topic)}`;
            link.removeAttribute('aria-disabled');
        } else {
            link.removeAttribute('href');
            link.setAttribute('aria-disabled', 'true');
        }
    });

    // Extracts the backend's own explanation of what went wrong (e.g. an
    // unmatchable address) so the user sees more than just an HTTP status
    // code -- falls back to a generic message if the body isn't JSON or
    // doesn't include one (e.g. a network-level failure).
    async function errorMessageFromResponse(response) {
        try {
            const data = await response.json();
            if (data && typeof data.message === 'string' && data.message) {
                return data.message;
            }
        } catch (err) {
            // Response body wasn't JSON (or was empty) -- fall through to the generic message.
        }
        return 'Something went wrong, please try again. (HTTP ' + response.status + ')';
    }

    // Feather icons (MIT licensed, https://feathericons.com), inlined so the
    // widget stays self-contained -- no icon font/sprite request.
    const PHONE_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>';
    const ARROW_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="7" y1="17" x2="17" y2="7"></line><polyline points="7 7 17 7 17 17"></polyline></svg>';
    const GLOBE_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>';

    function renderResults(data) {
        return renderGroup('Representatives', data.representatives, data.state_name, data.district, data.state)
             + renderGroup('Senators', data.senators, data.state_name);
    }

    function renderGroup(heading, people, stateName, district, stateCode) {
        if (!people.length) return '';
        const items = people.map(p => {
            let role = p.role;
            if (district === undefined) {
                if (stateName) role = `${p.role} of ${stateName}`;
            } else if (district !== '0') {
                role = stateCode
                    ? `${p.role} for ${stateCode}-${ordinal(district)} District`
                    : `${p.role} for the ${ordinal(district)} congressional district`;
            } else if (stateName) {
                role = `${p.role} for ${stateName}`;
            }
            return `<li class="cdl-person">
                ${p.photo_url ? `<img src="${p.photo_url}" alt="${p.display_name}" width="64" height="64">` : ''}
                <div>
                    <p class="cdl-name">${p.display_name}</p>
                    <p class="cdl-role">${role}</p>
                    <p class="cdl-meta">
                        <span class="cdl-party">${p.party}</span>
                        ${p.phone ? `<a class="cdl-icon-link" href="tel:${p.phone}" aria-label="Call ${p.phone}" title="${p.phone}">${PHONE_ICON}</a>` : ''}
                        ${p.website ? `<a class="cdl-icon-link" href="${p.website}" aria-label="Visit website" title="${p.website}">${GLOBE_ICON}</a>` : ''}
                    </p>
                    ${renderVoteTopics(p)}
                </div>
            </li>`;
        }).join('');
        return `<h3>${heading}</h3><ul>${items}</ul>`;
    }

    // Only voting House members: CivicDog has no Senate voting record yet, and
    // Delegates / the Resident Commissioner have no floor votes to search.
    function renderVoteTopics(p) {
        if (p.role !== 'Representative' || !p.bioguide_id || !voteTopics.length) return '';
        const options = voteTopicOptions();
        return `<div class="cdl-votes">
            <label><span class="cdl-votes-text">Votes on</span>
                <select class="cdl-topic" data-bioguide="${p.bioguide_id}" aria-label="See how ${p.display_name} voted on">
                    <option value="">Choose a topic&hellip;</option>
                    ${options}
                </select>
            </label>
            <a class="cdl-votes-link" target="_blank" rel="noopener" aria-disabled="true" aria-label="See ${p.display_name}'s votes on CivicDog" title="See votes on CivicDog">${ARROW_ICON}</a>
        </div>`;
    }

    // <option>s for the topic picker, with each "[Heading]" group from the
    // admin's list wrapped in an <optgroup>. Option values index into
    // voteTopics. Grouped topics are always contiguous, ungrouped ones first
    // (see cd_lookup_parse_vote_topics()).
    function voteTopicOptions() {
        let html = '';
        let open = null;
        voteTopics.forEach((t, i) => {
            if (t.group !== open) {
                if (open !== null) html += '</optgroup>';
                html += `<optgroup label="${t.group}">`;
                open = t.group;
            }
            html += `<option value="${i}">${t.label}</option>`;
        });
        return open !== null ? html + '</optgroup>' : html;
    }

    // District is not at-large ("0") -- e.g. 12 -> "12th", 1 -> "1st", 11 -> "11th".
    function ordinal(n) {
        n = Number(n);
        const suffixes = ['th', 'st', 'nd', 'rd'];
        const v = n % 100;
        return n + (suffixes[(v - 20) % 10] || suffixes[v] || suffixes[0]);
    }
}());
</script>
