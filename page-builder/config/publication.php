<?php

declare(strict_types=1);

/**
 * PB-DIST-3B — the single explicit publication-identity authority (distribution tooling only).
 *
 * This is developer/build-tool metadata, NOT Page Builder runtime config. It is deliberately NOT
 * loaded by the CMS service provider, container, or `config()` — the remote adapter reads it
 * directly. It answers exactly one question frozen by ADR-PB-GITHUB-REMOTE-ARCHITECTURE (PB-DIST-3A):
 * WHERE the certified standalone repository is published. It carries NO credentials — authentication
 * is external and ephemeral (§15 of the ADR).
 *
 * Every field here is explicit. Nothing is inferred from the ambient branch, an existing origin, a
 * global GitHub username, or the working directory (§19 / PB-DIST-3B Stage 11).
 */
return [
    'provider' => 'github',
    'repository_owner' => 'tncms',
    'repository_name' => 'page-builder',
    'remote_name' => 'origin',
    'default_branch' => 'main',
];
