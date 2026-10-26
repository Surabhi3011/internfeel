#!/usr/bin/env bash
# Public-repo check (docs/development.md section 1). This repo is public, so it fails if any file that
# git would commit contains an email address, a /home/ path, an AdSense publisher ID or a key-like
# string, other than the allowed placeholders below.
#
# Usage: tools/check-public.sh [private-values-file]
#   The optional file lists private values to look for (one plain string per line, such as the cPanel
#   username or personal emails). Keep it outside the repo or in docs-private/, never in git.
#   CI runs without it; run it with the file locally before every commit.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

self='tools/check-public.sh'
files=()
while IFS= read -r -d '' f; do
	[ "$f" = "$self" ] && continue
	[ -f "$f" ] || continue
	files+=( "$f" )
done < <(git ls-files -z --cached --others --exclude-standard)

fail=0

# report LABEL REGEX ALLOW_REGEX [GREP_EXCLUDE_FILE_REGEX]
# Prints every match of REGEX (file:line:match) whose matched text is not fully matched by ALLOW_REGEX.
report() {
	local label=$1 re=$2 allow=$3 skip=${4:-'^$'} hits
	hits=$(printf '%s\n' "${files[@]}" | grep -vE "$skip" | xargs -r grep -noIE -- "$re" 2>/dev/null \
		| grep -vE -- ":(${allow})\$" || true)
	if [ -n "$hits" ]; then
		echo "::error::Public-repo check: ${label}"
		echo "$hits"
		fail=1
	fi
}

# Email addresses. Allowed: the two public business addresses from the brief, git@github.com,
# GitHub noreply addresses and example domains. package-lock.json and composer.lock are skipped: they
# hold npm and Composer package authors' public metadata (copied from each package), not ours.
report 'email address' \
	'[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}' \
	'contact@internfeel\.com|no-reply@internfeel\.com|git@github\.com|[A-Za-z0-9._%+-]+@users\.noreply\.github\.com|[A-Za-z0-9._%+-]+@example\.(com|org|net)' \
	'^(package-lock\.json|composer\.lock)$'

# Home-folder paths. Allowed: the placeholder /home/<cpanel-user> and the bare words "/home/".
report '/home/ path (use $HOME or /home/<cpanel-user>)' '/home/[A-Za-z0-9._-]+' 'NONE^'

# AdSense publisher IDs. Allowed: the all-zero placeholder.
report 'AdSense publisher ID' 'ca-pub-[0-9]{16}' 'ca-pub-0{16}'

# Key-like strings.
report 'private key' 'BEGIN [A-Z ]*PRIVATE KEY' 'NONE^'
report 'GitHub token' 'gh[pousr]_[A-Za-z0-9]{36}|github_pat_[A-Za-z0-9_]{22,}' 'NONE^'
report 'Google API key' 'AIza[0-9A-Za-z_-]{35}' 'NONE^'
report 'AWS access key' 'AKIA[0-9A-Z]{16}' 'NONE^'
report 'Cloudflare Turnstile key' '0x4AAAA[A-Za-z0-9_-]{10,}' 'NONE^'
report 'WordPress salt or DB password' "define\\(\\s*['\"](AUTH|SECURE_AUTH|LOGGED_IN|NONCE)_(KEY|SALT)['\"]|define\\(\\s*['\"]DB_PASSWORD['\"]\\s*,\\s*['\"][^'\"]+" 'NONE^'

# Private values from a local file, if given.
if [ -n "${1:-}" ]; then
	if [ ! -r "$1" ]; then
		echo "Cannot read $1" >&2
		exit 2
	fi
	hits=$(printf '%s\n' "${files[@]}" | xargs -r grep -nIiF -f <(grep -v '^\s*$' "$1") 2>/dev/null || true)
	if [ -n "$hits" ]; then
		echo "::error::Public-repo check: private value from $1"
		echo "$hits" | cut -d: -f1,2
		fail=1
	fi
fi

if [ "$fail" -eq 0 ]; then
	echo "Public-repo check passed (${#files[@]} files)."
fi
exit "$fail"
