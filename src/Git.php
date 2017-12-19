<?php
/*
 * This file is part of Git.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SebastianBergmann\Git;

use DateTime;

class Git
{
    /**
     * @var string
     */
    private $repositoryPath;

    /**
     * Constructor (checks whether the given repository directory exists)
     * @param string $repositoryPath
     * @throws \Exception
     */
    public function __construct($repositoryPath)
    {
        if (!is_dir($repositoryPath)) {
            throw new Exception(
                sprintf(
                    'Directory "%s" does not exist',
                    $repositoryPath
                )
            );
        }

        $this->repositoryPath = realpath($repositoryPath);
    }

    /**
     * Checks out a specific revision
     * @param string $revision
     * @throws Exception
     */
    public function checkout($revision)
    {
        $this->execute(
            'checkout --force --quiet ' . $revision
        );
    }

    /**
     * Gets a string about the current branch
     * @return string
     * @throws Exception
     */
    public function getCurrentBranch()
    {
        $output = $this->execute('symbolic-ref --short HEAD');

        return $output[0];
    }

    /**
     * Gets a textual difference between two references
     * @param  string $from
     * @param  string $to
     * @return string
     * @throws Exception
     */
    public function getDiff($from, $to)
    {
        $output = $this->execute(
            'diff --no-ext-diff ' . $from . ' ' . $to
        );

        return implode("\n", $output);
    }

    /**
     * Get Git repository's log
     * @param string The order in which to show logs. ASC for chronological, DESC for the opposite
     * @param int Number of registers to recover. If set with ASC, the function will get ALL commits and then drop the ones we don't want
     * @return array
     * @throws Exception
     */
    public function getRevisions($order = 'DESC', $count = null)
    {
        $cmd = 'log --no-merges --date-order --format=medium';
        $countAndReverse = false;
        if (strcmp($order, 'DESC') !== 0) {
            // if ASC, get in chronological order
            $cmd .=  ' --reverse';
            if (!empty($count)) {
                //if ASC *and* $count != null, take note to apply patch on lines number
                $countAndReverse = true;
            }
        } elseif (!empty($count)) {
            $cmd .= ' -'.intval($count);
        }
        $output = $this->execute($cmd);

        $numLines  = count($output);
        $revisions = array();
        $author = '';
        $sha1 = '';
        $date = '';
        $message = '';

        for ($i = 0; $i < $numLines; $i++) {
            $tmp = explode(' ', $output[$i]);
            if ($countAndReverse && count($revisions) >= $count) {
                // if ASC and $count != null, and we already have $count, leave
                break;
            }

            if ($tmp[0] == 'commit') {
                // Save previous commit
                if ($i > 0) {
                    $revisions[] = array(
                        'author' => $author,
                        'date' => $date,
                        'sha1' => $sha1,
                        'message' => substr($message, 0, -1)
                    );
                }
                unset($author);
                unset($date);
                $message = '';

                // Treat the new commit
                $sha1 = $tmp[1];
            } elseif ($tmp[0] == 'Author:') {
                $author = implode(' ', array_slice($tmp, 1));
            } elseif ($tmp[0] == 'Date:' && isset($author) && isset($sha1)) {
                $date = DateTime::createFromFormat(
                    'D M j H:i:s Y O',
                    implode(' ', array_slice($tmp, 3))
                );
            } else {
                // In any other case, if the line is not empty, add to the
                // current message
                if (!empty($output[$i])) {
                    $message .= trim($output[$i]).' ';
                }
            }
        }
        if ($i > 0) {
            // Save last commit
            $revisions[] = array(
                'author' => $author,
                'date' => $date,
                'sha1' => $sha1,
                'message' => substr($message, 0, -1)
            );
        }

        return $revisions;
    }

    /**
     * Checks whether the working copy is clean
     * @return bool
     * @throws Exception
     */
    public function isWorkingCopyClean()
    {
        $output = $this->execute('status');

        return $output[count($output)-1] == 'nothing to commit, working directory clean' ||
               $output[count($output)-1] == 'nothing to commit, working tree clean';
    }

    /**
     * Executes a specific command prepared by other methods.
     * This prepends the "git" command, so the command sent cannot contain it
     * @param string $command
     *
     * @return string
     *
     * @throws Exception
     */
    protected function execute($command)
    {
        $command = 'cd ' . escapeshellarg($this->repositoryPath) . '; git ' . $command . ' 2>&1';
 
        if (DIRECTORY_SEPARATOR == '/') {
            $command = 'LC_ALL=en_US.UTF-8 ' . $command;
        }

        exec($command, $output, $returnValue);

        if ($returnValue !== 0) {
            throw new Exception(implode("\r\n", $output));
        }

        return $output;
    }
}
