# BGA Template

*This is an template for creating a new project on Board Game Arena.*


## Technology
* VS Code
* Sass and TypeScript


## Prerequesites
* Basic
  * [BGA Extensions Pack](https://marketplace.visualstudio.com/items?itemName=NevinFoster.bga-extension-pack)
    * SFTP
    * PHP Intelephense
    * PHP Debug
    * Prettier
  * [Live Sass Compiler](https://marketplace.visualstudio.com/items?itemName=glenn2223.live-sass)
  * Install typesript
  * npm install -g tsc-watch
* Extra
  * [PHP DocBlocker](https://marketplace.visualstudio.com/items?itemName=neilbrayfield.php-docblocker)
  * [PHP Refactor Tool](https://marketplace.visualstudio.com/items?itemName=st-pham.php-refactor-tool)
  * [PHPUnit Test Explorer](https://marketplace.visualstudio.com/items?itemName=recca0120.vscode-phpunit)


## TODO:
1. Create a game on BGA Studio
2. Create a new repo/folder; open in VS Code
3. Copy the .vscode folder only to it
4. Update intelephense paths in .vscode/settings.json if necessary
5. add password to .vscode/sftp.json
    * F1 -> "SFTP: Download Project"
6. Copy \_gitignore to folder and rename to ".gitignore"
7. Commit to your repo
8. Copy all the other files into the project; this will overwrite some files (which is why we committed the originals)
9. Replace all instances of "DevilsDice" with your game name.
10. Replace all instances of "devilsdice" with your games name in lowercase.
11. Replace all instances of "hedgehogterror" with your name (if you're not them)
12. Replace all instances of "brookelfnichols@gmail.com" with your email (if you're not them)
13. Search for github.com and replace with link to your repo
14. Rename file /src/sass/devilsdice.scss with your game name
15. Rename file /src/typescript/devilsdice.ts with your game name
16. Update gameinfos.inc.php with the basics
17. Diff with committed files to see if there were any changes in the framework that may require adjustments
18. Ensure that .css and .js files are generated
19. F1 -> "SFTP: Sync Local -> Remote"
20. Verify that your game runs on BGA Studio
21. Build your game!
