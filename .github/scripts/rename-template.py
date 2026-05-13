#!/usr/bin/env python3
"""Rename template placeholders when a new repo is created from the template."""

import os
import re


def to_kebab(name: str) -> str:
    return name.lower().replace("_", "-")


def to_snake(name: str) -> str:
    return name.lower().replace("-", "_")


def to_title(name: str) -> str:
    return " ".join(word.capitalize() for word in re.split(r"[-_]", name))


def to_pascal(name: str) -> str:
    return "".join(word.capitalize() for word in re.split(r"[-_]", name))


def sanitize_docker(name: str) -> str:
    if name[0].isdigit():
        name = "app-" + name
    return re.sub(r"[^a-z0-9-]", "-", name)


def main():
    repo_full = os.environ.get("GITHUB_REPOSITORY", "")
    if not repo_full:
        raise RuntimeError("GITHUB_REPOSITORY not set")

    repo_name = repo_full.split("/")[-1]
    kebab = sanitize_docker(to_kebab(repo_name))
    snake = to_snake(repo_name)
    title = to_title(repo_name)
    pascal = to_pascal(repo_name)

    replacements = [
        (
            "compose.yaml",
            [
                (r"^  frankenphp:\n", f"  {kebab}:\n"),
                (r"^    container_name: frankenphp\n", f"    container_name: {kebab}\n"),
            ],
        ),
        (
            ".devcontainer/devcontainer.json",
            [
                ('"name": "PHP Starter Kit"', f'"name": "{title}"'),
                ('"service": "frankenphp"', f'"service": "{kebab}"'),
            ],
        ),
        (
            "makefile",
            [
                (r"exec --user=robbyte frankenphp", f"exec --user=robbyte {kebab}"),
                (r"exec frankenphp cat", f"exec {kebab} cat"),
            ],
        ),
        (
            "build/prod/docker-entrypoint.sh",
            [
                ('echo "PHP Starter Kit - Starting..."', f'echo "{title} - Starting..."'),
            ],
        ),
        (
            "src/public/index.php",
            [
                ("<title>PHP Starter Kit — Setup Wizard</title>", f"<title>{title} — Setup Wizard</title>"),
                ('<div class="hero-badge">PHP Starter Kit</div>', f'<div class="hero-badge">{title}</div>'),
                ("PHP Starter Kit &copy;", f"{title} &copy;"),
            ],
        ),
        (
            "README.md",
            [
                ("# PHP Starter Kit", f"# {title}"),
                ("https://github.com/rdurica/php_starter_kit.git", f"https://github.com/{repo_full}.git"),
                ("https://github.com/rdurica/php_starter_kit/actions", f"https://github.com/{repo_full}/actions"),
                ("cd php_starter_kit", f"cd {repo_name}"),
            ],
        ),
        (
            "AGENTS.md",
            [
                ("# Agent Context for PHP Starter Kit", f"# Agent Context for {title}"),
                ("docker compose exec frankenphp", f"docker compose exec {kebab}"),
            ],
        ),
    ]

    for filepath, rules in replacements:
        if not os.path.exists(filepath):
            print(f"Warning: {filepath} not found, skipping")
            continue

        with open(filepath, "r", encoding="utf-8") as f:
            content = f.read()

        original = content
        for pattern, replacement in rules:
            flags = re.MULTILINE if pattern.startswith("^") else 0
            content = re.sub(pattern, replacement, content, flags=flags)

        if content != original:
            with open(filepath, "w", encoding="utf-8") as f:
                f.write(content)
            print(f"Updated: {filepath}")
        else:
            print(f"No changes: {filepath}")

    with open(".template-configured", "w", encoding="utf-8") as f:
        f.write(f"Configured from template for {repo_full}\n")
    print("Created: .template-configured")


if __name__ == "__main__":
    main()
