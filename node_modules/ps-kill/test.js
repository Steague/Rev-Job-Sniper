'use strict';

var expect = require('chai').expect;
var psKill = require('./index.js');
var noopProcess = require('noop-process');
var processExists = require('process-exists');

describe('ps-kill', function () {
  describe('#kill', function () {
    var options;

    beforeEach(function () {
      options = {
        persistent: false
      };
    });

    afterEach(function () {
      noopProcess.cleanup();
    });

    it('should kill a process with the same given PID', function (done) {
      noopProcess(options, function (error, pid) {
        psKill.kill(pid, function (error) {
          if (error) throw error;

          processExists(pid, function (error, exists) {
            expect(exists).to.be.false;

            done();
          });
        });
      });
    });
  });
});
