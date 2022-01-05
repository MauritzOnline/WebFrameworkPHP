#!/usr/bin/python3

import re

readme_file = open("./README.md", "r")
current_lines = readme_file.readlines()
readme_file.close()

toc_lines = [
    "<!-- DON'T edit this section, instead run \"generate_toc.py\" to update -->\n\n",
    "## Table of Contents\n\n",
]
toc_start = -1
toc_end = -1

for index, line in enumerate(current_lines):
    inside_toc = False
    if toc_start >= 0 and toc_end == -1:
        inside_toc = True
    elif toc_start >= 0 and index <= toc_end:
        inside_toc = True

    if line.startswith("<!-- START ToC -->"):
        toc_start = index
    elif line.startswith("<!-- END ToC -->"):
        toc_end = index
    elif line.startswith("##") and inside_toc == False:
        split_line = line.split(" ")
        if len(split_line) > 0:
            heading = line.replace(split_line[0], "").strip()
            heading_link = re.sub(
                r"[^a-z0-9\-_]+", "", heading.lower().replace(' ', '-'), flags=re.IGNORECASE)
            heading_size = len(split_line[0])
            toc_lines.append(
                f"{'  ' * (heading_size - 2)}- [{heading}](#{heading_link})\n")

toc_lines.append("\n")

if toc_start >= 0 and toc_end > toc_start:
    current_lines = current_lines[:(toc_start + 1)] + \
        toc_lines + current_lines[toc_end:]

    readme_file = open("./README.md", "w")
    readme_file.writelines(current_lines)
    readme_file.close()

    print(
        f"Wrote the following ToC lines at line {str(toc_start + 2)} to line {str(toc_end)}:\n")
    for line in toc_lines:
        print(line, end="")
else:
    print("Missing <!-- START ToC --> and/or <!-- END ToC --> tags!")
