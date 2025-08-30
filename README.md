```markdown
# local_digisign (Docuseal integration) — prototype

This Moodle local plugin provides a minimal integration with Docuseal:
- Displays available Docuseal templates as tiles.
- Creates a submission for the current user.
- Embeds Docuseal signing widget using the docuseal-form web component.
- On completion downloads signed PDF from Docuseal and stores it in the user's private files.
- Records submissions in local table local_digisign_sub to show completed state.

Deployment / development notes:
- Add your Docuseal API key in Site administration > Plugins > Local plugins > Digisign (this plugin).
- The implementation uses simple cURL server-side requests; extend with robust HTTP client and error handling for production.
- Update Docuseal API endpoints and response field mappings in lib.php/ajax.php if they differ from assumed shapes.
- This is a prototype: add full privacy provider actions, tests, UI, and error handling before production use.