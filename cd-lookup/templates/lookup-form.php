<div id="cd-lookup">
    <style>
        #cd-lookup {
            --cdl-navy: #263369;
            --cdl-green: #0d8a56;
            --cdl-tint: #eef1fa;
            --cdl-radius: 12px;
            --cdl-btn-radius: 30px;
            max-width: 640px;
        }
        #cd-lookup-form {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: .75em;
            background: var(--cdl-tint);
            border-radius: var(--cdl-radius);
            padding: 1.5em;
            margin: 0 0 1.5em;
        }
        #cd-lookup-form .cdl-field {
            flex: 1 1 260px;
        }
        #cd-lookup-form label {
            display: block;
            margin: 0 0 .4em;
            font-size: .8em;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--cdl-navy);
        }
        #cd-lookup-address {
            box-sizing: border-box;
            width: 100%;
            padding: .45em .9em;
            font-size: 1em;
            border: 1px solid rgba(38, 51, 105, .25);
            border-radius: var(--cdl-radius);
        }
        #cd-lookup-address:focus {
            outline: none;
            border-color: var(--cdl-navy);
        }
        #cd-lookup-form button {
            box-sizing: border-box;
            flex: none;
            padding: .45em 1.1em;
            font-family: 'Public Sans', Arial, sans-serif;
            font-size: 1em;
            font-weight: 600;
            letter-spacing: .065em;
            text-transform: uppercase;
            color: #fff;
            background: var(--cdl-navy);
            border: 1px solid transparent;
            border-radius: var(--cdl-btn-radius);
            cursor: pointer;
            white-space: nowrap;
            transition: background .3s ease-in-out, color .3s ease-in-out, transform .3s ease-in-out, box-shadow .3s ease-in-out;
        }
        #cd-lookup-form button .cdl-btn-text {
            font-size: .65em;
        }
        #cd-lookup-form button:hover {
            background: #d1d7ed;
            color: var(--cdl-navy);
            transform: translateY(-1px);
            box-shadow: 0 5px 10px rgba(0, 0, 0, .15);
        }
        #cd-lookup-results h3 {
            margin: 1.5em 0 1em;
            padding-bottom: .4em;
            font-size: 1em;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--cdl-navy);
            border-bottom: 3px solid var(--cdl-green);
        }
        #cd-lookup-results ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .cdl-person {
            display: flex;
            gap: 1em;
            margin: 0 0 1em;
            padding: 1em;
            background: #fff;
            border: 1px solid rgba(38, 51, 105, .12);
            border-radius: var(--cdl-radius);
        }
        .cdl-person img {
            flex: none;
            border-radius: var(--cdl-radius);
        }
        .cdl-person .cdl-name {
            margin: 0 0 .2em;
            font-weight: 700;
            color: var(--cdl-navy);
        }
        .cdl-person .cdl-role {
            margin: 0 0 .5em;
            font-size: .8em;
            color: #555;
        }
        .cdl-person .cdl-meta {
            display: flex;
            align-items: center;
            gap: .6em;
            margin: 0;
            font-size: .9em;
        }
        .cdl-person .cdl-party {
            display: inline-block;
            padding: .2em .6em;
            font-size: .75em;
            font-weight: 600;
            letter-spacing: .03em;
            text-transform: uppercase;
            color: var(--cdl-navy);
            background: var(--cdl-tint);
            border-radius: 1em;
        }
        .cdl-person a {
            color: var(--cdl-green);
            text-decoration: none;
        }
        .cdl-person a:hover {
            color: var(--cdl-navy);
        }
        .cdl-icon-link {
            display: inline-flex;
        }
        .cdl-icon-link svg {
            width: 16px;
            height: 16px;
        }
        .cdl-person .cdl-votes {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .5em;
            margin: .8em 0 0;
            font-size: .85em;
            color: var(--cdl-navy);
        }
        .cdl-person .cdl-votes select {
            max-width: 100%;
            padding: .3em .5em;
            font-size: 1em;
            border: 1px solid rgba(38, 51, 105, .25);
            border-radius: 8px;
        }
        .cdl-person .cdl-votes-link {
            padding: .35em 1em;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #fff;
            background: var(--cdl-navy);
            border-radius: var(--cdl-btn-radius);
            white-space: nowrap;
            transition: background .3s ease-in-out, color .3s ease-in-out;
        }
        .cdl-person .cdl-votes-link:hover {
            color: var(--cdl-navy);
            background: #d1d7ed;
        }
        .cdl-person .cdl-votes-link[aria-disabled="true"] {
            opacity: .45;
            pointer-events: none;
        }
    </style>

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
    const civicdogUrl = <?php echo wp_json_encode( rtrim( get_option( 'cd_lookup_civicdog_app_url', CD_LOOKUP_CIVICDOG_APP_URL_DEFAULT ), '/' ) ); ?>;
    // Admin-curated topics (Settings > CD Lookup). `label` is pre-escaped for
    // innerHTML; the raw `topic` only ever goes through encodeURIComponent.
    const voteTopics  = <?php echo wp_json_encode( array_map( fn ( $topic ) => [ 'label' => cd_lookup_esc( $topic ), 'topic' => $topic ], cd_lookup_vote_topics() ) ); ?>;

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

    // Point a card's "See votes" link at the chosen topic on CivicDog (which
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
    const GLOBE_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>';

    function renderResults(data) {
        return renderGroup('Senators', data.senators, data.state_name)
             + renderGroup('Representatives', data.representatives, data.state_name, data.district);
    }

    function renderGroup(heading, people, stateName, district) {
        if (!people.length) return '';
        const items = people.map(p => {
            let role = p.role;
            if (district === undefined) {
                if (stateName) role = `${p.role} of ${stateName}`;
            } else if (district !== '0') {
                role = stateName
                    ? `${p.role} for ${stateName}'s ${ordinal(district)} congressional district`
                    : `${p.role} for the ${ordinal(district)} congressional district`;
            } else if (stateName) {
                role = `${p.role} for ${stateName}`;
            }
            return `<li class="cdl-person">
                ${p.photo_url ? `<img src="${p.photo_url}" alt="${p.display_name}" width="80" height="80">` : ''}
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
        const options = voteTopics.map((t, i) => `<option value="${i}">${t.label}</option>`).join('');
        return `<div class="cdl-votes">
            <label>See how ${p.display_name} voted on
                <select class="cdl-topic" data-bioguide="${p.bioguide_id}">
                    <option value="">Choose a topic&hellip;</option>
                    ${options}
                </select>
            </label>
            <a class="cdl-votes-link" target="_blank" rel="noopener" aria-disabled="true">See votes &#8599;</a>
        </div>`;
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
