<div id="cd-lookup">
    <form id="cd-lookup-form">
        <label for="cd-lookup-address">Street Address</label>
        <input
            type="text"
            id="cd-lookup-address"
            name="address"
            placeholder="123 Main St, City, State ZIP"
            required
        >
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
            `<li>
                <a href="https://www.govtrack.us${p.profile_url}">${p.full_name}</a>
                &mdash; ${p.role}<br>
                ${p.party} &bull; <a href="tel:${p.phone}">${p.phone}</a>
                &bull; <a href="${p.website}">${p.website}</a>
            </li>`
        ).join('');
        return `<h3>${heading}</h3><ul>${items}</ul>`;
    }
}());
</script>
