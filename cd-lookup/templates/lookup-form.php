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
            padding: .7em .9em;
            font-size: 1em;
            border: 1px solid rgba(38, 51, 105, .25);
            border-radius: var(--cdl-radius);
        }
        #cd-lookup-address:focus {
            outline: none;
            border-color: var(--cdl-navy);
        }
        #cd-lookup-form button {
            flex: none;
            padding: .8em 1.8em;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #fff;
            background: var(--cdl-green);
            border: 0;
            border-radius: var(--cdl-btn-radius);
            cursor: pointer;
            white-space: nowrap;
            transition: background .3s ease-in-out, transform .3s ease-in-out, box-shadow .3s ease-in-out;
        }
        #cd-lookup-form button:hover {
            background: var(--cdl-navy);
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
            font-size: .9em;
            color: #555;
        }
        .cdl-person .cdl-meta {
            margin: 0;
            font-size: .9em;
        }
        .cdl-person .cdl-party {
            display: inline-block;
            margin-right: .5em;
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
    </style>

    <form id="cd-lookup-form">
        <div class="cdl-field">
            <label for="cd-lookup-address">Street Address</label>
            <input
                type="text"
                id="cd-lookup-address"
                name="address"
                placeholder="123 Main St, City, State ZIP"
                required
            >
        </div>
        <button type="submit">Look Up Representatives</button>
    </form>

    <div id="cd-lookup-results" hidden></div>
</div>

<script>
(function () {
    const endpoint = <?php echo wp_json_encode( rest_url( 'cd-lookup/v1/representatives' ) ); ?>;
    const nonce    = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;

    document.getElementById('cd-lookup-form').addEventListener('submit', async function (e) {
        e.preventDefault();

        const address = document.getElementById('cd-lookup-address').value.trim();
        const results = document.getElementById('cd-lookup-results');

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
                throw new Error('Request failed: ' + response.status);
            }

            const data = await response.json();
            results.innerHTML = renderResults(data);
        } catch (err) {
            results.innerHTML = '<p>Error: ' + err.message + '</p>';
        }
    });

    function renderResults(data) {
        return renderGroup('Senators', data.senators)
             + renderGroup('Representatives', data.representatives);
    }

    function renderGroup(heading, people) {
        if (!people.length) return '';
        const items = people.map(p =>
            `<li class="cdl-person">
                ${p.photo_url ? `<img src="https://www.govtrack.us${p.photo_url}" alt="${p.full_name}" width="80" height="80">` : ''}
                <div>
                    <p class="cdl-name">${p.full_name}</p>
                    <p class="cdl-role">${p.role}</p>
                    <p class="cdl-meta">
                        <span class="cdl-party">${p.party}</span>
                        ${p.phone ? `<a href="tel:${p.phone}">${p.phone}</a>` : ''}
                        ${p.website ? `&bull; <a href="${p.website}">${p.website}</a>` : ''}
                    </p>
                </div>
            </li>`
        ).join('');
        return `<h3>${heading}</h3><ul>${items}</ul>`;
    }
}());
</script>
