import os
import re
import requests
from functools import partial

# Set the global API URL
API_URL = 'http://127.0.0.1:47813/WebFrameworkPHP/test_webframeworkphp'
FILE_TO_UPLOAD = "test_webframeworkphp/test_files/to_upload.txt"
FILE_TO_SAVE = "test_webframeworkphp/test_files/downloaded.txt"

# Define the regex pattern to match the string representation of a function
func_reg = re.compile(r"<function (\w+) at 0x[0-9a-f]+>", flags=re.IGNORECASE)


class Colors:
    HEADER = '\033[95m'
    OKBLUE = '\033[94m'
    OKCYAN = '\033[96m'
    OKGREEN = '\033[92m'
    WARNING = '\033[93m'
    FAIL = '\033[91m'
    ENDC = '\033[0m'
    BOLD = '\033[1m'
    UNDERLINE = '\033[4m'


tests_to_run: list[partial] = []
current_test_response = ""
current_test_status_code = 0


def test_404(mode: int):
    global current_test_response
    global current_test_status_code

    assert mode in [
        0, 1, 2], 'Invalid mode passed to "test_404" function (valid ones: 0, 1, 2)!'

    response = None
    base_url = f'{API_URL}/should_not_exist'

    match mode:
        case 0:
            response = requests.get(base_url)
        case 1:
            response = requests.post(base_url)
        case 2:
            response = requests.delete(base_url)

    # Check that response has ben received
    assert response != None, "Response not received!"

    current_test_response = response.text
    current_test_status_code = response.status_code

    # Check that the response status code is 404
    assert response.status_code == 404, "HTTP status code is not 404!"

    # Check that the response content contains the expected text
    match mode:
        case 0:
            assert 'Hello <strong>HTML 404</strong> here!' in response.text, "Response did not contain the expected text"
        case 1:
            assert response.text == '404 - not found (custom POST)!', "Response did not match the expected text"
        case 2:
            assert response.text == '404 - not found (custom ALL)!', "Response did not match the expected text"


def test_uri_params(include_ending_slash: bool, include_url_query: bool, include_second_url_param: bool, run_html_version: bool):
    global current_test_response
    global current_test_status_code

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
    current_test_response = response.text
    current_test_status_code = response.status_code

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


def test_auth_token(mode: int):
    global current_test_response
    global current_test_status_code

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
    current_test_response = response.text
    current_test_status_code = response.status_code

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


def test_post_data(data_type: int):
    global current_test_response
    global current_test_status_code

    assert data_type in [
        0, 1, 2], 'Invalid "data_type" passed to "test_post_data" function (valid ones: 0, 1, 2)!'

    json_headers = {
        "Content-Type": "application/json"
    }

    data = {
        "field1": "123",
        "field2": "abc"
    }
    files_data = {
        "field1": (None, "123"),
        "field2": (None, "abc")
    }

    response = None

    # Make a POST request to the API
    match data_type:
        case 0:  # form-data
            response = requests.post(
                f'{API_URL}/post_data', files=files_data)
        case 1:  # x-www-form-urlencoded
            response = requests.post(f'{API_URL}/post_data', data=data)
        case 2:  # raw[application/json]
            response = requests.post(
                f'{API_URL}/post_data', json=data, headers=json_headers)

    # Check that response has ben received
    assert response != None, "Response not received!"

    current_test_response = response.text
    current_test_status_code = response.status_code

    # Check that the response status code is 200 (OK)
    assert response.status_code == 200, "HTTP status code is not 200!"

    # Check that the response contains the expected data
    assert response.json() == data, "Response does not contain the expected values!"


def test_file_upload(stream: bool):
    global current_test_response
    global current_test_status_code

    file_contents = "INVALID"

    with open(FILE_TO_UPLOAD, "r") as file:
        file_contents = file.read()

    files_data = {
        "field1": (None, "123abc"),
        "file1": ("to_upload.txt", open(FILE_TO_UPLOAD, "rb"))
    }

    response = requests.post(
        f'{API_URL}/upload_file', files=files_data, stream=stream)
    current_test_response = response.text
    current_test_status_code = response.status_code

    # Check that the response status code is 200 (OK)
    assert response.status_code == 200, "HTTP status code is not 200!"

    files_data["field1"] = files_data["field1"][1]
    files_data["file1"] = file_contents

    # print(f"Response: \"{response.json()}\", Expected: \"{files_data}\"")

    # Check that the response contains the expected data
    assert response.json() == files_data, "Response does not contain the expected values!"


def test_file_download(stream: bool):
    global current_test_response
    global current_test_status_code

    base_url = f'{API_URL}/download_file'

    if stream == True:
        base_url += "/stream"

    response = requests.get(base_url, stream=stream)
    current_test_response = response.text
    current_test_status_code = response.status_code

    # print(response.text)

    # Check that the response status code is 200 (OK)
    assert response.status_code == 200, "HTTP status code is not 200!"

    # Save the file
    with open(FILE_TO_SAVE, "wb") as file:
        if stream == True:
            for chunk in response.iter_content(chunk_size=1024):
                file.write(chunk)
        else:
            file.write(response.content)

    file_contents = "INVALID"

    # Check the file
    with open(FILE_TO_SAVE, "r") as file:
        file_contents = file.read()

    try:
        os.remove(FILE_TO_SAVE)
    except FileNotFoundError:
        print("No downloaded file found to delete!")

    assert file_contents == "bye world!", "Downloaded file content does not match expected value!"


def test_send_json(run_body_version: bool, include_status_code: bool, status_code: int):
    global current_test_response
    global current_test_status_code

    assert status_code >= 100, "Status code cannot be less than 100!"
    assert status_code < 600, "Status code cannot be greater than 599!"

    url_queries = {
        "run_body_version": run_body_version,
        "include_status_code": include_status_code,
        "status_code": status_code
    }

    data = {
        "field1": "123",
        "field2": "abc"
    }

    if run_body_version == True and include_status_code == True:
        data["status"] = status_code

    response = requests.post(f'{API_URL}/send_json',
                             params=url_queries, data=data)
    current_test_response = response.text
    current_test_status_code = response.status_code

    # Check that the response status code is "status_code"
    assert response.status_code == status_code, "HTTP status code is not {} (is: {})!".format(
        status_code, response.status_code)

    """print(" ")
    print(
        f"[ ] test_send_json(run_body_version: {run_body_version}, include_status_code: {include_status_code}, status_code: {status_code}) running")
    # print(response.json())
    print(f"Response: \"{response.json()}\", Expected: \"{data}\"")"""

    # Check that the response contains the expected data
    if include_status_code == True:
        data["status"] = status_code

    assert response.json() == data, "Response does not contain the expected values!"


# Define the list of parameter values
bool_values = [False, True]
status_code_values = [200, 400, 404, 500]

# ============================== Start of test adding ==============================
# Iterate over all combinations of parameter values for: test_404
for i in range(0, 3):
    tests_to_run.append(partial(test_404, i))

# Iterate over all combinations of parameter values for: test_uri_params
for include_ending_slash in bool_values:
    for include_url_query in bool_values:
        for include_second_url_param in bool_values:
            for run_html_version in bool_values:
                tests_to_run.append(partial(test_uri_params, include_ending_slash,
                                    include_url_query, include_second_url_param, run_html_version))

# Iterate over all combinations of parameter values for: test_auth_token
for i in range(-1, 2):
    tests_to_run.append(partial(test_auth_token, i))

# Iterate over all combinations of parameter values for: test_post_data
for i in range(0, 3):
    tests_to_run.append(partial(test_post_data, i))

# Iterate over all combinations of parameter values for: test_file_upload & test_file_download
for stream in bool_values:
    tests_to_run.append(partial(test_file_upload, stream))
    tests_to_run.append(partial(test_file_download, stream))

# Iterate over all combinations of parameter values for: test_send_json
for run_body_version in bool_values:
    for include_status_code in bool_values:
        for status_code in status_code_values:
            tests_to_run.append(
                partial(test_send_json, run_body_version, include_status_code, status_code))
# ==============================  End of test adding  ==============================

print(f"{Colors.OKBLUE}>>  Running {len(tests_to_run)} tests  <<{Colors.ENDC}")
print("")

# Call all the test functions
for i, test_to_run in enumerate(tests_to_run):
    clean_function_name = func_reg.sub(r"\1", str(test_to_run.func))
    function_call = f"{Colors.OKCYAN}{clean_function_name}{str(test_to_run.args).replace(',)', ')')}{Colors.ENDC}"
    print(
        f"{Colors.OKCYAN}[ ]{Colors.ENDC} Running: {function_call} ({i + 1}/{len(tests_to_run)})")
    try:
        test_to_run()
    except AssertionError as err:
        print(
            f"{Colors.FAIL}[✗]{Colors.ENDC} Errored: {function_call} ({i + 1}/{len(tests_to_run)})")
        print("")
        print(
            f'{Colors.OKBLUE}Server response:{Colors.ENDC} "{current_test_response}" {Colors.OKCYAN}(status code: {current_test_status_code}){Colors.ENDC}')
        print(
            f'{Colors.FAIL}Assertion Error:{Colors.ENDC} "{err}"')
        exit()
    print(
        f"{Colors.OKGREEN}[✓]{Colors.ENDC} Cleared: {function_call} ({i + 1}/{len(tests_to_run)})")
    print("")

print(f"{Colors.OKGREEN}✓ All {len(tests_to_run)} tests cleared{Colors.ENDC}")
