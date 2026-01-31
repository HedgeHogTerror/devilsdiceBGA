# How to Clean Up node_modules from BGA Server

Since node_modules might already be on the BGA server, you need to delete them.

## Option 1: Via SFTP Client
1. Open an SFTP client (like FileZilla or Cyberduck)
2. Connect to: 1.studio.boardgamearena.com:2022
3. Username: hedgehogterror
4. Navigate to /devilsdice/
5. Delete the node_modules folder if it exists

## Option 2: Via VS Code SFTP Extension
1. Open Command Palette (Cmd+Shift+P)
2. Type "SFTP: List"
3. Navigate to the remote directory
4. Delete node_modules folder

## Option 3: Contact BGA Support
If you can't delete the files, you may need to ask BGA support to clear your project directory.

## After Cleanup
Once node_modules is deleted from the server:
1. Make sure .bgaignore is uploaded to the server
2. Try creating a release build again
