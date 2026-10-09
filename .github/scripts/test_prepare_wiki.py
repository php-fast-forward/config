import os
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

SCRIPT = Path(__file__).with_name('prepare-wiki.py')

class PrepareWikiTests(unittest.TestCase):
    def test_resolves_local_pages_and_preserves_external_links_and_anchors(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'classes/Example').mkdir(parents=True)
            (root / 'Home.md').write_text('[Thing](./classes/Example/Thing#run) [Web](https://example.test/help)\n', encoding='utf-8')
            (root / 'classes/Example/Thing.md').write_text('[Home](../../Home) [Part](#run)\n', encoding='utf-8')
            result = subprocess.run([sys.executable, str(SCRIPT), str(root), 'https://github.com/acme/demo/wiki', 'https://github.com/acme/demo/commit/abc'], capture_output=True, text=True, encoding='utf-8', check=False)
            self.assertEqual(0, result.returncode, result.stderr)
            self.assertIn('[Thing](https://github.com/acme/demo/wiki/Thing#run)', (root / 'Home.md').read_text(encoding='utf-8'))
            self.assertIn('[Web](https://example.test/help)', (root / 'Home.md').read_text(encoding='utf-8'))
            self.assertIn('[Home](https://github.com/acme/demo/wiki/Home)', (root / 'classes/Example/Thing.md').read_text(encoding='utf-8'))
            self.assertIn('[Part](#run)', (root / 'classes/Example/Thing.md').read_text(encoding='utf-8'))
            self.assertIn('https://github.com/acme/demo/commit/abc', (root / 'Home.md').read_text(encoding='utf-8'))

    def test_excludes_agent_instruction_pages_from_generated_output(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'nested').mkdir()
            (root / 'Home.md').write_text('API docs\n', encoding='utf-8')
            (root / 'nested/AGENTS.md').write_text('agent instructions\n', encoding='utf-8')
            result = subprocess.run([sys.executable, str(SCRIPT), str(root), 'https://github.com/acme/demo/wiki', 'https://github.com/acme/demo/commit/abc'], capture_output=True, text=True, encoding='utf-8', check=False)
            self.assertEqual(0, result.returncode, result.stderr)
            self.assertFalse((root / 'nested/AGENTS.md').exists())

    def test_rejects_ambiguous_wiki_page_names_without_rewriting_content(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'a').mkdir()
            (root / 'b').mkdir()
            (root / 'Home.md').write_text('API docs\n', encoding='utf-8')
            (root / 'a/Thing.md').write_text('first\n', encoding='utf-8')
            (root / 'b/Thing.md').write_text('second\n', encoding='utf-8')
            result = subprocess.run([sys.executable, str(SCRIPT), str(root), 'https://github.com/acme/demo/wiki', 'https://github.com/acme/demo/commit/abc'], capture_output=True, text=True, encoding='utf-8', check=False)
            self.assertNotEqual(0, result.returncode)
            self.assertIn("Ambiguous Wiki page name", result.stderr)
            self.assertEqual('API docs\n', (root / 'Home.md').read_text(encoding='utf-8'))
            self.assertEqual('first\n', (root / 'a/Thing.md').read_text(encoding='utf-8'))


    def test_preserves_utf8_content_under_an_ascii_locale(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            content = 'Configuração — São Paulo\n'
            (root / 'Home.md').write_text(content, encoding='utf-8')
            environment = os.environ.copy()
            environment.update({'LC_ALL': 'C', 'PYTHONUTF8': '0', 'PYTHONCOERCECLOCALE': '0'})
            result = subprocess.run([sys.executable, str(SCRIPT), str(root), 'https://github.com/acme/demo/wiki', 'https://github.com/acme/demo/commit/abc'], capture_output=True, text=True, encoding='utf-8', check=False, env=environment)
            self.assertEqual(0, result.returncode, result.stderr)
            self.assertIn(content, (root / 'Home.md').read_text(encoding='utf-8'))

    def test_preserves_titles_and_rewrites_parenthesized_destinations(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'Home.md').write_text('[Page](./Thing(Info) "API page")\n', encoding='utf-8')
            (root / 'Thing(Info).md').write_text('API docs\n', encoding='utf-8')
            result = subprocess.run([sys.executable, str(SCRIPT), str(root), 'https://github.com/acme/demo/wiki', 'https://github.com/acme/demo/commit/abc'], capture_output=True, text=True, encoding='utf-8', check=False)
            self.assertEqual(0, result.returncode, result.stderr)
            self.assertIn('[Page](https://github.com/acme/demo/wiki/Thing%28Info%29 "API page")', (root / 'Home.md').read_text(encoding='utf-8'))

if __name__ == '__main__':
    unittest.main()
