const fs = require('fs');
const child = require('child_process');
const argv = require('minimist')(process.argv.slice(2));

const changelogPath = './changelog.md';
const pluginFiles = ['rh-free-layouts.php'];
const blacklist = ['Merge branch ', 'prepare-commit-msg', 'pre-commit-msg', '#ignore', 'cleanup'];


/**
 * Get the plugin version from a file
 * @param {String} pluginFile
 */
function getPluginVersion( pluginFile ) {
  const matches = String(pluginFile).match(/^ \* Version: ([0-9|.].*)/m);
  const version = matches ? matches[1] : null;
  return version;
}

/**
 * Add a commit message to the changelog
 *
 * @param {string} pluginVersion
 * @param {string} message
 * @param {object} changelog
 */
function addCommitMessageToChangelog( pluginVersion, message, changelog = {}, date = null ) {
  if( isMessageBlacklisted(message) ) return changelog;
  if( !changelog[pluginVersion] ) changelog[pluginVersion] = {
    date: date,
    messages: []
  };
  changelog[pluginVersion].messages.push( message );
  return changelog;
}

/**
 * Check if a message is in the blacklist
 * @param {string} message
 */
function isMessageBlacklisted(message) {
  for( const substring of blacklist ) {
    if( message.indexOf(substring) !== -1 ) return true;
  }
  return false;
}

function getCommits() {
  const delimiter = "----DELIMITER----";
  // https://git-scm.com/docs/pretty-formats
  const output = child.execSync(`git log --pretty=format:'%B###%H###%ad\n${delimiter}'`).toString('utf-8');

  const commits = output.split(`\n${delimiter}`).map(commit => {
    let [message, hash, date] = commit.split('###');
    message = message.replace(/(?:\r\n|\r|\n)/g, ' ').trim();
    return { message, hash, date };
  }).filter(commit => Boolean(commit.hash));
  return commits;
}

/**
 *
 * @param {string} file
 * @param {string} commit
 */
function fileExistsInCommit(file, hash) {
  try { child.execSync(`git cat-file -e ${hash}:${file} > /dev/null 2>&1`); return true; } catch(e) { return false; }
}

/**
 * Get the main plugin file from a certain commit
 *
 * @param {string} hash
 */
function getPluginFileInCommit(hash) {
  for( const file of pluginFiles ) {
    if( fileExistsInCommit(file, hash) ) {
      return child.execSync(`git show ${hash}:${file}`);
    }
  }
  return false;
}

/**
 * Generate the changelog
 */
async function generateChangelog() {
  const commitsArray = getCommits();
  let changelog = {};
  let lastCommit = null;

  for( const commit of Object.values(commitsArray) ) {
    // continue;
    let pluginFile = getPluginFileInCommit(commit.hash);
    if( !pluginFile ) continue;
    pluginFile = pluginFile.toString('utf-8');
    if( lastCommit && lastCommit.message === commit.message ) continue;
    lastCommit = commit;
    const date = new Date(commit.date).toISOString().split('T')[0];
    const shortHash = commit.hash.substr(0,7);
    const message = `${commit.message} (#${shortHash})`;
    const pluginVersion = getPluginVersion( pluginFile );
    if( !pluginVersion ) continue;
    changelog = addCommitMessageToChangelog( pluginVersion, message, changelog, date );
  }
  return changelog;
}

/**
 * Write the changelog
 * @param {object} changelog
 */
async function writeChangelog( changelog ) {
  let file = '';
  for( const [version, versionInfo] of Object.entries(changelog) ) {
    file += `#### ${version} (${versionInfo.date})\n\n`;
    for( const change of versionInfo.messages ) {
      file += `- ${change}\n`;
    }
    file += `\n`;
  }
  fs.writeFileSync(changelogPath, file);
  child.execSync(`git add ${changelogPath}`);
  child.execSync(`git commit --amend -C HEAD --no-verify ${changelogPath}`);
}

generateChangelog().then(changelog => writeChangelog(changelog));