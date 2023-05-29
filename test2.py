
exit()
def foo() -> str:
    return 'x'


x = []
i = 10_000_000;
while i > 0:
    i -= 1
    x.append(foo())

x = ''.join(x)

print(f"{len(x)} bytes\n")
