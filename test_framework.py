import requests

# Set the global API URL
API_URL = 'http://127.0.0.1:47813/WebFrameworkPHP/test_webframeworkphp'


def test_get_404():
    # Make a GET request to a non-existent endpoint
    response = requests.get(f'{API_URL}/should_not_exist')

    # Check that the response status code is 404
    assert response.status_code == 404, "HTTP status code is not 404!"

    # Check that the response content contains the expected text
    assert 'Hello <strong>HTML 404</strong> here!' in response.text, "Response did not contain the expected text"

    print("✓ test_get_404 cleared")


def test_post_404():
    # Make a POST request to a non-existent endpoint
    response = requests.post(f'{API_URL}/should_not_exist')

    # Check that the response status code is 404
    assert response.status_code == 404, "HTTP status code is not 404!"

    # Check that the response content is an exact match for the expected text
    assert response.text == '404 - not found (custom POST)!', "Response did not match the expected text"

    print("✓ test_post_404 cleared")


def test_delete_404():
    # Make a DELETE request to a non-existent endpoint
    response = requests.delete(f'{API_URL}/should_not_exist')

    # Check that the response status code is 404
    assert response.status_code == 404, "HTTP status code is not 404!"

    # Check that the response content is an exact match for the expected text
    assert response.text == '404 - not found (custom ALL)!', "Response did not match the expected text"

    print("✓ test_delete_404 cleared")


def test_uri_params(include_ending_slash, include_url_query, include_second_url_param, run_html_version):
    # Set the base URL
    base_url = f'{API_URL}/uri_params'

    if run_html_version:
        base_url += '/html'
    else:
        base_url += '/json'

    if include_second_url_param:
        base_url += '/123/abc'
    else:
        base_url += '/123abc'

    # Add a slash to the end of the URL if required
    if include_ending_slash:
        base_url += '/'

    # Add URL params to the end of the URL if required
    if include_url_query:
        base_url += '?hello=world&lorem=ipsum'

    # Make a GET request to the API
    response = requests.get(base_url)

    # Check that the response status code is 200 (OK)
    assert response.status_code == 200, "HTTP status code is not 200!"

    if run_html_version:
        # Check that the response content is HTML (uses "in" since ";charset=UTF-8" also get included)
        assert 'text/html' in response.headers['Content-Type'], "Content-Type is not text/html!"
    else:
        # Check that the response content is a JSON object
        assert response.headers['Content-Type'] == 'application/json', "Content-Type is not application/json!"

    if run_html_version:
        # Check that the response contains the expected data
        if include_second_url_param:
            assert '<p class="param1">123</p>' in response.text, "Missing or invalid param1 in response!"
            assert '<p class="param2">abc</p>' in response.text, "Missing or invalid param2 in response!"
        else:
            assert '<p class="param1">123abc</p>' in response.text, "Missing or invalid param1 in response!"

        if include_url_query:
            assert '<pre class="query">{"hello":"world","lorem":"ipsum"}</pre>' in response.text, "Missing or invalid query in response!"
        else:
            assert '<pre class="query">[]</pre>' in response.text, "Missing or invalid query in response!"
    else:
        # Check that the response JSON contains the expected data
        data = response.json()

        if include_second_url_param:
            assert 'param1' in data, "Missing param1 in response JSON!"
            assert data['param1'] == '123', 'param1 does not match the expected value "123"'

            assert 'param2' in data, "Missing param2 in response JSON!"
            assert data['param2'] == 'abc', 'param2 does not match the expected value "abc"'
        else:
            assert 'param1' in data, "Missing param1 in response JSON!"
            assert data['param1'] == '123abc', 'param1 does not match the expected value "123abc"'

        if include_url_query:
            assert 'query' in data, "Missing query in response JSON!"
            assert data['query'] == {
                'hello': 'world', 'lorem': 'ipsum'}, "URL query does not contain the expected values!"
        else:
            assert 'query' in data, "Missing query in response JSON!"
            assert data['query'] == [
            ], "URL query is not an empty array as expected!"

    print(
        f"✓ test_uri_params(include_ending_slash: {include_ending_slash}, include_url_query: {include_url_query}, include_second_url_param: {include_second_url_param}, run_html_version: {run_html_version}) cleared")


def test_auth_token(mode):
    assert mode in [-1, 0,
                    1], 'Invalid mode passed to "test_auth_token" function (valid ones: -1, 0, 1)!'
    headers = {}

    match mode:
        case -1:
            headers["Authorization"] = "Bearer INVALID_TOKEN"
        case 0:
            pass
        case 1:
            headers["Authorization"] = "Bearer my_valid_secret_token"

    # Make a GET request to the API
    response = requests.get(f'{API_URL}/bearer', headers=headers)

    # Check that the response status code is 200 (OK)
    assert response.status_code == 200, "HTTP status code is not 200!"

    # Check that the response content is plaintext (uses "in" since ";charset=UTF-8" also get included)
    assert 'text/plain' in response.headers['Content-Type'], "Content-Type is not text/plain!"

    # Check that the response content is an exact match for the expected text
    match mode:
        case -1:
            assert response.text == 'Invalid auth token!', "Response did not match the expected text"
        case 0:
            assert response.text == 'Missing valid auth token!', "Response did not match the expected text"
        case 1:
            assert response.text == 'my_valid_secret_token', "Response did not match the expected text"

    print(f"✓ test_auth_token(mode: {mode}) cleared")


# Call all the functions
test_get_404()
test_post_404()
test_delete_404()


# Define the list of parameter values
param_values = [False, True]

# Iterate over all combinations of parameter values
for include_ending_slash in param_values:
    for include_url_query in param_values:
        for include_second_url_param in param_values:
            for run_html_version in param_values:
                # Call the function with the current combination of parameter values
                test_uri_params(include_ending_slash,
                                include_url_query, include_second_url_param, run_html_version)

for i in range(-1, 2):
    test_auth_token(i)

print("✓ All tests cleared")
