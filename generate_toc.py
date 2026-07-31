#!/usr/bin/python3

import re
from collections import defaultdict

MARKDOWN_FILE_PATH = "./README.md"
markdown_file = open(MARKDOWN_FILE_PATH, "r")
current_lines = markdown_file.readlines()
markdown_file.close()


def slugify(text):
    # Mirror GitHub's slugifier: lowercase, strip anything that isn't a
    # word char / space / hyphen, then turn spaces into hyphens.
    slug = text.strip().lower()
    slug = re.sub(r"[^\w\s-]", "", slug)
    slug = re.sub(r"\s+", "-", slug)
    return slug


# Tracks how many times each base slug has been seen, so duplicate
# headings get "-1", "-2", ... suffixes exactly like GitHub does.
_seen_slugs = defaultdict(int)


def unique_slug(text):
    base = slugify(text)
    count = _seen_slugs[base]
    _seen_slugs[base] += 1
    return base if count == 0 else f"{base}-{count}"


toc_title = ""
toc_lines = [
    "<!-- DON'T edit this section, instead run \"generate_toc.py\" to update -->\n\n",
    "## Table of Contents {TITLE}\n\n",
]
toc_start = -1
toc_end = -1
in_code_block = False
last_fence_line = -1

# Parse for headings
for index, line in enumerate(current_lines):
    stripped = line.strip()

    # Toggle code-block state on fenced code markers (``` or ~~~).
    # Lines inside code blocks must never be treated as headings.
    if stripped.startswith("```") or stripped.startswith("~~~"):
        in_code_block = not in_code_block
        last_fence_line = index
        continue

    if in_code_block:
        continue

    inside_toc = False
    under_toc = False

    if toc_start >= 0 and toc_end == -1:
        inside_toc = True
    elif toc_start >= 0 and index <= toc_end:
        inside_toc = True

    if toc_end != -1 and index > toc_end:
        under_toc = True

    if line.startswith("<!-- START ToC -->"):
        toc_start = index

        for key, toc_line in enumerate(toc_lines):
            toc_line = toc_line.replace(" {TITLE}", "")
            toc_lines[key] = toc_line
    elif line.startswith("<!-- START ToC | "):
        toc_start = index
        toc_title_split = line.split("|")
        toc_title = toc_title_split[-1].replace("-->", "").strip()

        for key, toc_line in enumerate(toc_lines):
            toc_line = toc_line.replace("{TITLE}", f'| `{toc_title}`')
            toc_lines[key] = toc_line
    elif line.startswith("<!-- END ToC -->"):
        toc_end = index
    elif line.startswith("##"):
        split_line = line.split(" ")
        # Ensure the first token is purely "#"s (a real ATX heading),
        # e.g. "##" or "###", and that a space follows it.
        if len(split_line) > 1 and set(split_line[0]) == {"#"}:
            # Parse heading text (strip only the leading "#"s token)
            heading = line.replace(split_line[0], "", 1).strip()

            # Register the slug for EVERY level >= 2 heading in document
            # order (even ones we don't render in the ToC), so duplicate
            # suffixes (-1, -2, ...) match what GitHub generates.
            heading_link = unique_slug(heading)

            # Only emit ToC entries for headings located after the ToC block.
            if not inside_toc and under_toc:
                heading_size = len(split_line[0])
                toc_lines.append(
                    f"{'  ' * (heading_size - 2)}- [{heading}](#{heading_link})\n")

toc_lines.append("\n")

# Validate that all code fences were balanced. An unclosed fence would have
# inverted the in_code_block state, causing headings to be silently skipped
# (or non-headings to be parsed). Warn loudly rather than write a broken ToC.
if in_code_block:
    print(
        "WARNING: Unbalanced code fence detected! An opening ``` or ~~~ "
        f"around line {last_fence_line + 1} was never closed.\n"
        "         The generated ToC may be incomplete or incorrect. "
        "Aborting without writing changes.\n")
elif toc_start >= 0 and toc_end > toc_start:
    # Replace everything inside ToC tags with Table of Contents
    current_lines = current_lines[:(toc_start + 1)] + \
        toc_lines + current_lines[toc_end:]

    # Write changes to file
    markdown_file = open(MARKDOWN_FILE_PATH, "w")
    markdown_file.writelines(current_lines)
    markdown_file.close()

    print(
        f"Wrote the following ToC lines at line {str(toc_start + 2)} to line {str(toc_end)}:\n")
    for line in toc_lines:
        print(line, end="")
else:
    print("Missing <!-- START ToC --> and/or <!-- END ToC --> tags!")