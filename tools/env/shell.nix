{ naviEnv
, withOpenSwoole ? false
}:
import (naviEnv + "/shell.nix") {
  lockFile = naviEnv + "/flake.lock";
  withQualityTools = true;
  extraExtensions = { all, ... }:
    if withOpenSwoole then [ all.openswoole ] else [];
  name = "darkwood-flow";
}
