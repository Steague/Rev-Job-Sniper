# ps-kill [![Build Status](https://drone.io/github.com/kraftz/ps-kill/status.png)](https://drone.io/github.com/kraftz/ps-kill/latest)
> Kill processes with ease

## Install

```
$ npm install --save ps-kill
```

## Usage

```javascript
var psKill = require('ps-kill');

psKill.kill(pid, function (error) {
  if (!error) console.log('gone :)');
});
```

## API

### kill(pid, [callback])

#### pid
Type: number, string

Process ID to be killed.

## License

MIT
