#!/usr/bin/env bash
set -euo pipefail

artifact_dir="$(cd "$1" && pwd)"
version="$(jq -er '.version' "$artifact_dir/release.json")"
commit="$(jq -er '.commit' "$artifact_dir/release.json")"
archive="$(jq -er '.archive' "$artifact_dir/release.json")"

# Distinguish a missing ref from authentication, network, and API failures.
if gh api "repos/$GITHUB_REPOSITORY/git/ref/tags/$version" > "$artifact_dir/tag.json" 2> "$artifact_dir/tag-error.log"; then
    echo "Tag $version already exists. Use a new package version." >&2
    exit 1
elif ! grep -q 'HTTP 404' "$artifact_dir/tag-error.log"; then
    cat "$artifact_dir/tag-error.log" >&2
    exit 1
fi

gh api --paginate "repos/$GITHUB_REPOSITORY/releases?per_page=100" \
    --jq '.[].tag_name' > "$artifact_dir/release-tags.txt"
if grep -Fxq "$version" "$artifact_dir/release-tags.txt"; then
    echo "A release or draft for $version already exists. Inspect it before retrying." >&2
    exit 1
fi

(cd "$artifact_dir" && sha256sum --check SHA256SUMS)
if ! gh release create "$version" "$artifact_dir/$archive" "$artifact_dir/SHA256SUMS" \
    --repo "$GITHUB_REPOSITORY" --draft --target "$commit" --title "$version" \
    --notes "Validated Marketplace package from commit $commit. Publish this draft after review, then submit the attached ZIP through the Adobe Developer Portal."; then
    echo "Draft creation or asset upload failed. Inspect the release page for a partial draft before retrying." >&2
    exit 1
fi
gh release view "$version" --repo "$GITHUB_REPOSITORY" --json url,isDraft,targetCommitish
